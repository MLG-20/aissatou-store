<?php

namespace App\Services;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use App\Enums\Order\WhatsAppTemplate;
use App\Exceptions\OrderException;
use App\Jobs\SendOrderWhatsApp;
use App\Mail\NewOrderForAdmin;
use App\Mail\OrderPlaced;
use App\Mail\OrderStatusChanged;
use App\Models\Admin;
use App\Models\Cart\Cart;
use App\Models\Catalogue\ProductVariant;
use App\Models\Content\SiteSetting;
use App\Models\Customer\Address;
use App\Models\Delivery\Courier;
use App\Models\Delivery\DeliveryZone;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\Payment\PaymentManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly StockService $stock,
        private readonly PromoService $promos,
    ) {}

    /**
     * Crée la commande depuis le panier du client, puis vide le panier.
     *
     * Code promo (Phase 6C) : revalidé ICI sur le panier final, sous verrou —
     * un code accepté à la saisie puis rendu invalide (panier modifié sous le
     * minimum, code expiré ou épuisé entre-temps) fait échouer la commande.
     *
     * @throws OrderException
     */
    public function createFromCart(User $user, Cart $cart, Address $address, string $providerKey, ?string $promoCode = null, bool $whatsappOptIn = false): Order
    {
        abort_unless($address->user_id === $user->id, 403);

        $provider = $this->payments->find($providerKey);

        throw_unless($provider && $provider->isAvailable(), OrderException::paymentUnavailable());

        $order = DB::transaction(function () use ($user, $cart, $address, $provider, $promoCode, $whatsappOptIn) {
            $items = $cart->items()->with(['product', 'variant'])->get();

            throw_if($items->isEmpty(), OrderException::emptyCart());

            $lines = [];
            $promoLines = [];
            $reserved = [];

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

                    // Décrément après création de la commande (le mouvement
                    // de stock la référence) ; le verrou est tenu d'ici là. Les
                    // quantités déjà réservées dans CE panier sont déduites,
                    // au cas où une déclinaison y figurerait deux fois.
                    $alreadyReserved = collect($reserved)
                        ->filter(fn (array $entry) => $entry[0]->id === $variant->id)
                        ->sum(fn (array $entry) => $entry[1]);

                    throw_if(
                        ! $locked || $locked->stock - $alreadyReserved < $item->quantity,
                        OrderException::insufficientStock()
                    );

                    $reserved[] = [$locked, $item->quantity];
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

                $promoLines[] = [
                    'product_id' => $product->id,
                    'category_id' => $product->category_id,
                    'amount' => $unitPrice * $item->quantity,
                ];
            }

            $subtotal = collect($lines)->sum(fn (array $line) => $line['unit_price'] * $line['quantity']);

            // Remise sur les articles uniquement, jamais sur la livraison.
            $promo = filled($promoCode) ? $this->promos->reserve($promoCode, $user, $promoLines) : null;
            $discount = $promo?->discount ?? 0.0;

            // Frais de livraison recalculés côté serveur depuis la ville de
            // l'adresse (jamais depuis le client) et figés avec le reste.
            $zone = DeliveryZone::forCity($address->city);
            $shipping = $zone ? (float) $zone->fee : 0;

            $order = Order::create([
                'user_id' => $user->id,
                'number' => $this->generateNumber(),
                'status' => OrderStatus::New,
                'payment_status' => PaymentStatus::Pending,
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'promo_code' => $promo?->code->code,
                'shipping_total' => $shipping,
                'shipping_zone' => $zone?->name,
                'total' => $subtotal - $discount + $shipping,
                'recipient_name' => $address->recipient_name,
                'phone' => $address->phone,
                'city' => $address->city,
                'address_line' => $address->address_line,
                'landmark' => $address->landmark,
                ...$address->locationAttributes(),
                // Suivi WhatsApp : seulement avec l'accord explicite du client.
                'whatsapp_opt_in' => $whatsappOptIn,
                'whatsapp_opt_in_at' => $whatsappOptIn ? now() : null,
            ]);

            $order->items()->createMany($lines);

            if ($promo) {
                $this->promos->recordRedemption($promo, $order, $user);
            }

            foreach ($reserved as [$locked, $quantity]) {
                $this->stock->removeForOrder($locked, $quantity, $order);
            }

            $order->update(['payment_status' => $provider->initiate($order)]);

            $order->history()->create([
                'from_status' => null,
                'to_status' => OrderStatus::New->value,
            ]);

            $cart->items()->delete();

            $order = $order->fresh(['items', 'payment', 'user']);

            return $order;
        });

        // Après commit uniquement : un rollback ne doit jamais avoir envoyé
        // d'email. File d'attente (phase 3) : le tunnel ne bloque pas dessus.
        Mail::to($order->user)->queue(new OrderPlaced($order));

        // Paiement en ligne : la boutique sera alertée à la confirmation
        // (PaymentService::confirm), pas pour un panier qui peut être abandonné.
        // Même règle pour WhatsApp : « paiement confirmé » tiendra lieu de
        // confirmation (décision Ahmed, 2026-10-28).
        if (! $provider->isOnline()) {
            Mail::to($this->shopEmail())->queue(new NewOrderForAdmin($order));
            SendOrderWhatsApp::dispatchFor($order, WhatsAppTemplate::OrderConfirmed);
        }

        return $order;
    }

    /**
     * Changement de statut par l'admin ou par le livreur depuis sa page :
     * transition validée + traçabilité (qui, quand, ancien → nouveau) pour
     * les litiges.
     *
     * @throws OrderException
     */
    public function changeStatus(Order $order, OrderStatus $to, ?int $authorId = null, ?Courier $courier = null): Order
    {
        throw_unless($order->status->canTransitionTo($to), OrderException::invalidTransition());

        $order = DB::transaction(function () use ($order, $to, $authorId, $courier) {
            $from = $order->status;

            $order->update(['status' => $to]);

            // À la livraison en paiement à la livraison, l'argent est encaissé :
            // les deux statuts avancent ensemble, mais restent distincts.
            if ($to === OrderStatus::Delivered && $order->payment?->provider === 'cod') {
                // confirmed_at = date d'encaissement, base du chiffre
                // d'affaires (Phase 6D).
                $order->payment->update(['status' => PaymentStatus::Confirmed, 'confirmed_at' => now()]);
                $order->update(['payment_status' => PaymentStatus::Confirmed]);
            }

            // Annulation (admin ou délai de paiement dépassé) : les articles
            // réservés à la création sont remis en vente (décision Ahmed,
            // 2026-10-23), mouvement journalisé (Phase 6B).
            if ($to === OrderStatus::Cancelled) {
                $this->stock->restoreForOrder($order, $authorId ? Admin::find($authorId) : null);
                // Code promo : l'utilisation est rendue (décision Ahmed, 6C).
                $this->promos->release($order);
            }

            $order->history()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'author_id' => $authorId,
                'courier_id' => $courier?->id,
            ]);

            return $order->fresh(['payment', 'history', 'user', 'items']);
        });

        Mail::to($order->user)->queue(new OrderStatusChanged($order, $to));

        if (($template = WhatsAppTemplate::forStatus($to)) && ! $this->isAbandonedOnlinePayment($order)) {
            SendOrderWhatsApp::dispatchFor($order, $template);
        }

        return $order;
    }

    /**
     * Commande payable en ligne jamais payée : le client n'a reçu aucune
     * confirmation WhatsApp, son annulation ne lui est donc pas annoncée.
     */
    private function isAbandonedOnlinePayment(Order $order): bool
    {
        return $this->payments->find((string) $order->payment?->provider)?->isOnline()
            && $order->payment_status !== PaymentStatus::Confirmed;
    }

    /**
     * Adresse opérationnelle de la boutique (réglée en admin, Contenu du site)
     * : c'est là qu'arrivent les alertes de nouvelles commandes.
     */
    public function shopEmail(): string
    {
        return SiteSetting::current()->contact_email ?? config('mail.from.address');
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
