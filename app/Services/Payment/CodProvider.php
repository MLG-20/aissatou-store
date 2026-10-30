<?php

namespace App\Services\Payment;

use App\Enums\Order\PaymentStatus;
use App\Models\Content\SiteSetting;
use App\Models\Order\Order;
use App\Models\Order\Payment;

/**
 * Paiement à la livraison : premier fournisseur de la phase 3. Aucun
 * appel externe — le paiement reste « en attente » jusqu'à la remise, où
 * l'admin le confirme en passant la commande à « livrée ».
 */
class CodProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'cod';
    }

    public function label(): string
    {
        return 'Paiement à la livraison';
    }

    /** Interrupteur de Réglages du site, coupé par défaut. */
    public function isAvailable(): bool
    {
        return SiteSetting::current()->cash_on_delivery_enabled;
    }

    public function initiate(Order $order): PaymentStatus
    {
        $order->payment()->create([
            'provider' => $this->key(),
            'status' => PaymentStatus::Pending,
            'amount' => $order->total,
        ]);

        return PaymentStatus::Pending;
    }

    public function checkout(Payment $payment): ?string
    {
        return null;
    }

    public function isOnline(): bool
    {
        return false;
    }

    /** Aucune source externe : seul l'admin constate l'encaissement. */
    public function verify(Payment $payment): ?PaymentStatus
    {
        return null;
    }
}
