<?php

namespace App\Services;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use App\Exceptions\OrderException;
use App\Models\Cart\Cart;
use App\Models\Catalogue\ProductVariant;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\Payment\PaymentManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Toute la logique métier de création et de suivi de commande (phase 3),
 * calquée sur le pattern CartService/AddressService : le contrôleur
 * orchestre, le service décide.
 *
 * Garanties :
 * - le total est TOUJOURS recalculé côté serveur depuis le catalogue — jamais
 *   accepté depuis le client (un montant manipulé ne peut pas passer) ;
 * - la création est atomique (transaction + verrous) : stock décrémenté sans
 *   commande créée, ou commande sans paiement, c'est impossible ;
 * - deux commandes simultanées sur le dernier exemplaire ne survendent pas :
 *   la seconde trouve un stock insuffisant au moment du verrou et échoue.
 */
class OrderService
{
    public function __construct(private readonly PaymentManager $payments) {}

    /**
     * Crée la commande depuis le panier du client, puis vide le panier.
     *
     * @throws OrderException
     */
    public function createFromCart(User $user, Cart $cart, Address $address, string $providerKey): Order
    {
        abort_unless($address->user_id === $user->id, 403);

        $provider = $this->payments->find($providerKey);

        throw_unless($provider && $provider->isAvailable(), OrderException::paymentUnavailable());

        return DB::transaction(function () use ($user, $cart, $address, $provider) {
            $items = $cart->items()->with(['product', 'variant'])->get();

            throw_if($items->isEmpty(), OrderException::emptyCart());

            $lines = [];

            foreach ($items as $item) {
                $product = $item->product;

                throw_if(! $product || ! $product->is_active, OrderException::productUnavailable());

                $variant = $item->variant;

                if ($variant && ($variant->product_id !== $product->id || ! $variant->is_active)) {
                    throw OrderException::productUnavailable();
                }

                if ($product->variants()->exists() && ! $variant) {
                    throw OrderException::productUnavailable();
                }

                // Verrou + relecture du stock DANS la transaction : c'est ce qui
                // empêche la survente quand deux clients valident ensemble.
                if ($variant) {
                    $locked = ProductVariant::whereKey($variant->id)->lockForUpdate()->first();

                    throw_if(! $locked || $locked->stock < $item->quantity, OrderException::insufficientStock());

                    $locked->decrement('stock', $item->quantity);
                }

                // Prix figé DEPUIS LA BASE, pas depuis le panier (qui ne stocke
                // rien) et surtout pas depuis la requête.
                $unitPrice = (float) ($variant?->price_override ?? $product->price);

                $lines[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'variant_label' => $variant ? $this->variantLabel($variant) : null,
                    'sku' => $variant?->sku ?? $product->sku,
                    'unit_price' => $unitPrice,
                    'quantity' => $item->quantity,
                ];
            }

            $subtotal = collect($lines)->sum(fn (array $line) => $line['unit_price'] * $line['quantity']);

            $order = Order::create([
                'user_id' => $user->id,
                'number' => $this->generateNumber(),
                'status' => OrderStatus::New,
                'payment_status' => PaymentStatus::Pending,
                'subtotal' => $subtotal,
                'shipping_total' => 0,
                'total' => $subtotal,
                'recipient_name' => $address->recipient_name,
                'phone' => $address->phone,
                'city' => $address->city,
                'address_line' => $address->address_line,
                'landmark' => $address->landmark,
            ]);

            $order->items()->createMany($lines);

            $order->update(['payment_status' => $provider->initiate($order)]);

            $order->history()->create([
                'from_status' => null,
                'to_status' => OrderStatus::New->value,
            ]);

            $cart->items()->delete();

            return $order->fresh(['items', 'payment']);
        });
    }

    /**
     * Changement de statut par l'admin : transition validée + traçabilité
     * (qui, quand, ancien → nouveau) pour les litiges.
     *
     * @throws OrderException
     */
    public function changeStatus(Order $order, OrderStatus $to, ?int $authorId = null): Order
    {
        throw_unless($order->status->canTransitionTo($to), OrderException::invalidTransition());

        return DB::transaction(function () use ($order, $to, $authorId) {
            $from = $order->status;

            $order->update(['status' => $to]);

            // À la livraison en paiement à la livraison, l'argent est encaissé :
            // les deux statuts avancent ensemble, mais restent distincts.
            if ($to === OrderStatus::Delivered && $order->payment?->provider === 'cod') {
                $order->payment->update(['status' => PaymentStatus::Confirmed]);
                $order->update(['payment_status' => PaymentStatus::Confirmed]);
            }

            $order->history()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'author_id' => $authorId,
            ]);

            return $order->fresh(['payment', 'history']);
        });
    }

    /**
     * Numéro lisible et non séquentiel (AS-2026-X7K4PQ) : pas devinable par
     * incrément, et la vérification d'appartenance reste obligatoire côté
     * contrôleur de toute façon.
     */
    private function generateNumber(): string
    {
        do {
            $number = 'AS-'.now()->year.'-'.Str::upper(Str::random(6));
        } while (Order::where('number', $number)->exists());

        return $number;
    }

    private function variantLabel(ProductVariant $variant): string
    {
        return collect($variant->attributes ?? [])
            ->map(fn ($value, $key) => "{$key} : {$value}")
            ->implode(', ');
    }
}
