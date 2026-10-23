<?php

namespace App\Services;

use App\Enums\Order\PromoCodeType;
use App\Exceptions\PromoCodeException;
use App\Models\Cart\Cart;
use App\Models\Catalogue\Category;
use App\Models\Order\Order;
use App\Models\Order\PromoCode;
use App\Models\Order\PromoCodeRedemption;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Règles des codes promo (Phase 6C, décisions Ahmed 2026-10-23) :
 * - un code par commande, remise sur les articles uniquement (jamais sur
 *   la livraison) ;
 * - minimum d'achat comparé au sous-total articles du panier entier ;
 * - avec ciblage (produits et/ou catégories, sous-catégories directes
 *   incluses), la remise ne porte que sur les lignes éligibles ;
 * - % arrondi au franc ; montant fixe plafonné au montant éligible (la
 *   remise ne dépasse jamais ce qu'elle réduit) ;
 * - utilisation rendue si la commande est annulée.
 *
 * Anti-abus : `quote()` (saisie du code) n'est qu'un aperçu. La décision
 * se prend dans `reserve()`, DANS la transaction de commande, sous verrou
 * de la ligne du code, avec revalidation complète sur le panier final et un
 * incrément conditionnel en SQL (`uses_count < max_uses`) — deux commandes
 * simultanées ne peuvent pas dépasser le plafond.
 */
class PromoService
{
    /**
     * Aperçu pour l'affichage du tunnel (non engageant).
     *
     * @param  list<array{product_id: int, category_id: ?int, amount: float}>  $lines
     *
     * @throws PromoCodeException
     */
    public function quote(string $rawCode, User $user, array $lines): PromoQuote
    {
        $code = PromoCode::where('code', PromoCode::normalize($rawCode))->first();

        throw_unless($code, PromoCodeException::invalid());

        return $this->evaluate($code, $user, $lines);
    }

    /**
     * Décision finale, à appeler DANS la transaction de création de commande :
     * verrou, revalidation, consommation atomique d'une utilisation.
     *
     * @param  list<array{product_id: int, category_id: ?int, amount: float}>  $lines
     *
     * @throws PromoCodeException
     */
    public function reserve(string $rawCode, User $user, array $lines): PromoQuote
    {
        $code = PromoCode::where('code', PromoCode::normalize($rawCode))->lockForUpdate()->first();

        throw_unless($code, PromoCodeException::invalid());

        $quote = $this->evaluate($code, $user, $lines);

        // Ceinture et bretelles : même si une lecture concurrente avait vu un
        // compteur périmé, l'UPDATE conditionnel est atomique côté base.
        $consumed = PromoCode::whereKey($code->id)
            ->where(fn ($query) => $query->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses'))
            ->increment('uses_count');

        throw_unless($consumed === 1, PromoCodeException::exhausted());

        return $quote;
    }

    /** Trace l'utilisation, une fois la commande créée (même transaction). */
    public function recordRedemption(PromoQuote $quote, Order $order, User $user): PromoCodeRedemption
    {
        return PromoCodeRedemption::create([
            'promo_code_id' => $quote->code->id,
            'order_id' => $order->id,
            'user_id' => $user->id,
            'discount' => $quote->discount,
        ]);
    }

    /** Annulation de commande : l'utilisation est rendue (une seule fois). */
    public function release(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $redemption = PromoCodeRedemption::where('order_id', $order->id)
                ->whereNull('released_at')
                ->lockForUpdate()
                ->first();

            if (! $redemption) {
                return;
            }

            $redemption->update(['released_at' => now()]);
            PromoCode::whereKey($redemption->promo_code_id)->where('uses_count', '>', 0)->decrement('uses_count');
        });
    }

    /**
     * Lignes d'un panier au format attendu (prix depuis le catalogue, jamais
     * depuis la requête — même règle que OrderService).
     *
     * @return list<array{product_id: int, category_id: ?int, amount: float}>
     */
    public static function linesFromCart(Cart $cart): array
    {
        return $cart->items()->with(['product', 'variant'])->get()
            ->filter(fn ($item) => $item->product !== null)
            ->map(fn ($item) => [
                'product_id' => $item->product->id,
                'category_id' => $item->product->category_id,
                'amount' => (float) ($item->variant?->price_override ?? $item->product->price) * $item->quantity,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{product_id: int, category_id: ?int, amount: float}>  $lines
     *
     * @throws PromoCodeException
     */
    private function evaluate(PromoCode $code, User $user, array $lines): PromoQuote
    {
        throw_unless($code->is_active, PromoCodeException::invalid());
        throw_if($code->starts_at?->isFuture(), PromoCodeException::notStarted());
        throw_if($code->ends_at?->isPast(), PromoCodeException::expired());
        throw_if(
            $code->max_uses !== null && $code->uses_count >= $code->max_uses,
            PromoCodeException::exhausted()
        );

        if ($code->once_per_customer) {
            $used = $code->redemptions()->where('user_id', $user->id)->whereNull('released_at')->exists();
            throw_if($used, PromoCodeException::alreadyUsed());
        }

        $subtotal = collect($lines)->sum('amount');

        throw_if(
            $code->min_subtotal !== null && $subtotal < (float) $code->min_subtotal,
            PromoCodeException::minimumNotReached((float) $code->min_subtotal)
        );

        $eligible = $this->eligibleSubtotal($code, $lines);

        throw_if($eligible <= 0, PromoCodeException::notApplicable());

        $discount = $code->type === PromoCodeType::Percent
            ? round($eligible * (float) $code->value / 100)
            : min((float) $code->value, $eligible);

        return new PromoQuote($code, (float) $discount, $eligible);
    }

    /** @param  list<array{product_id: int, category_id: ?int, amount: float}>  $lines */
    private function eligibleSubtotal(PromoCode $code, array $lines): float
    {
        $productIds = $code->products()->pluck('products.id')->all();
        $categoryIds = $code->categories()->pluck('categories.id')->all();

        if (! $productIds && ! $categoryIds) {
            return (float) collect($lines)->sum('amount');
        }

        // Une catégorie ciblée couvre aussi ses sous-catégories directes.
        if ($categoryIds) {
            $categoryIds = array_merge(
                $categoryIds,
                Category::whereIn('parent_id', $categoryIds)->pluck('id')->all()
            );
        }

        $productIds = array_map('intval', $productIds);
        $categoryIds = array_map('intval', $categoryIds);

        return (float) collect($lines)
            ->filter(fn (array $line) => in_array((int) $line['product_id'], $productIds, true)
                || ($line['category_id'] !== null && in_array((int) $line['category_id'], $categoryIds, true)))
            ->sum('amount');
    }
}
