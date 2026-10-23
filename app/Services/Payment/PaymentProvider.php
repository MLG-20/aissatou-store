<?php

namespace App\Services\Payment;

use App\Enums\Order\PaymentStatus;
use App\Models\Order\Order;
use App\Models\Order\Payment;

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
     * Appelée DANS la transaction de création de commande : aucun appel
     * réseau ici (les verrous de stock seraient tenus pendant l'attente).
     */
    public function initiate(Order $order): PaymentStatus;

    /**
     * Démarre le paiement chez le fournisseur, APRÈS le commit de la
     * commande. Renvoie l'URL où envoyer le client, ou null s'il n'y a rien
     * à payer en ligne (paiement à la livraison).
     *
     * @throws PaymentException fournisseur injoignable ou refus d'initier
     */
    public function checkout(Payment $payment): ?string;

    /**
     * Payé en ligne avant traitement (PayTech) plutôt qu'à la remise : la
     * boutique n'est alertée qu'une fois le paiement confirmé.
     */
    public function isOnline(): bool;

    /**
     * Statut RÉEL du paiement interrogé chez le fournisseur (vérification
     * active), ou null si rien à interroger / fournisseur injoignable.
     */
    public function verify(Payment $payment): ?PaymentStatus;
}
