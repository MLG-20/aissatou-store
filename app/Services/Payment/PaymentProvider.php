<?php

namespace App\Services\Payment;

use App\Enums\Order\PaymentStatus;
use App\Models\Order\Order;

/**
 * Contrat que tout moyen de paiement respecte (cahier des charges, section
 * 5.3) : le tunnel de commande et le back-office ne connaissent que cette
 * interface, jamais les détails d'un fournisseur. Ajouter Wave ou Orange
 * Money en phase 5 = une classe de plus, zéro modification du tunnel.
 */
interface PaymentProvider
{
    /** Clé stockée en base (`payments.provider`). */
    public function key(): string;

    /** Libellé affiché au client dans le tunnel. */
    public function label(): string;

    /** Disponible et correctement configuré pour encaisser. */
    public function isAvailable(): bool;

    /**
     * Enregistre le paiement initial de la commande et renvoie son statut.
     * Appelée DANS la transaction de création de commande.
     */
    public function initiate(Order $order): PaymentStatus;
}
