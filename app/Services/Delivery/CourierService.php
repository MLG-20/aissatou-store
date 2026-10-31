<?php

namespace App\Services\Delivery;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use App\Exceptions\OrderException;
use App\Models\Delivery\Courier;
use App\Models\Order\Order;
use App\Services\OrderService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\URL;

/**
 * Ce que le livreur fait depuis son téléphone, sans compte : « j'ai le colis,
 * je pars » puis « livrée ». Chaque appui passe par OrderService (transition
 * contrôlée, WhatsApp et email à la cliente) avec le livreur en auteur.
 */
class CourierService
{
    /** Durée de validité du lien d'une commande. */
    public const ORDER_LINK_DAYS = 3;

    public function __construct(private readonly OrderService $orders) {}

    /** Le livreur ne voit que les commandes qui lui sont confiées, tant qu'il est actif. */
    public function canHandle(?Courier $courier, Order $order): bool
    {
        return $courier !== null && $courier->is_active && $order->courier_id === $courier->id;
    }

    /** @throws OrderException */
    public function pickUp(Order $order, Courier $courier): Order
    {
        throw_unless($order->status === OrderStatus::Preparing, OrderException::invalidTransition());

        return $this->orders->changeStatus($order, OrderStatus::Shipped, courier: $courier);
    }

    /** @throws OrderException */
    public function deliver(Order $order, Courier $courier): Order
    {
        throw_unless($order->status === OrderStatus::Shipped, OrderException::invalidTransition());

        return $this->orders->changeStatus($order, OrderStatus::Delivered, courier: $courier);
    }

    /**
     * Lien signé de la page d'une commande. Signature relative : il marche
     * sur l'adresse d'où l'admin l'envoie (local, ngrok ou production), et
     * il meurt si le livreur change (son id fait partie de la signature).
     */
    public function orderLink(Order $order): string
    {
        return url(URL::temporarySignedRoute(
            'courier.order',
            now()->addDays(self::ORDER_LINK_DAYS),
            ['order' => $order->number, 'courier' => $order->courier_id],
            absolute: false,
        ));
    }

    /** Itinéraire : le point exact s'il existe, sinon l'adresse écrite. */
    public function directionsUrl(Order $order): string
    {
        return $order->locationUrl()
            ?? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($order->address_line.', '.$order->city.', Sénégal');
    }

    /** Montant que le livreur doit encaisser (paiement à la livraison non réglé). */
    public function amountToCollect(Order $order): ?float
    {
        return $order->payment?->provider === 'cod' && $order->payment_status !== PaymentStatus::Confirmed
            ? (float) $order->total
            : null;
    }

    /** Message WhatsApp pré-rempli vers le livreur, envoyé depuis le WhatsApp de la boutique. */
    public function whatsAppUrl(Order $order): ?string
    {
        $phone = PhoneNumber::toE164($order->courier?->phone);

        if ($phone === null) {
            return null;
        }

        $lines = array_filter([
            'Bonjour '.$order->courier->name.', livraison '.$order->number.' :',
            '👤 '.$order->recipient_name.' — '.$order->phone,
            '📍 '.$order->address_line.', '.$order->city,
            $order->landmark ? 'Repère : '.$order->landmark : null,
            ($amount = $this->amountToCollect($order)) !== null
                ? '💰 À encaisser : '.number_format($amount, 0, ',', ' ').' FCFA'
                : '✅ Déjà payée, rien à encaisser',
            '',
            'Itinéraire et validation (« je pars », « livrée ») :',
            $this->orderLink($order),
        ], fn (?string $line) => $line !== null);

        return 'https://wa.me/'.ltrim($phone, '+').'?text='.rawurlencode(implode("\n", $lines));
    }
}
