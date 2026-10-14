<?php

namespace App\Services\Payment;

use App\Enums\Order\PaymentStatus;
use App\Models\Order\Order;

/**
 * Paiement à la livraison : premier et seul fournisseur de la phase 3. Aucun
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

    public function isAvailable(): bool
    {
        return true;
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
}
