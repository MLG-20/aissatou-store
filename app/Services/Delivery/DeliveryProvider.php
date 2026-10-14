<?php

namespace App\Services\Delivery;

/**
 * Contrat que tout transporteur respecte (cahier des charges, section 5.4) :
 * le tunnel et le back-office ne connaissent que cette interface. Le mode
 * manuel est opérationnel dès la Phase 4 ; Yango (ou un autre) se branche
 * en Phase 4.x sans toucher au reste, dès que la cliente a ses accès.
 */
interface DeliveryProvider
{
    /** Clé stockée en base si besoin (`orders.delivery_provider`). */
    public function key(): string;

    /** Libellé affiché en admin. */
    public function label(): string;

    /** Configuré et joignable pour chiffrer/créer des envois. */
    public function isAvailable(): bool;

    /**
     * Devis en FCFA pour une ville, ou null si le fournisseur ne couvre pas.
     * Doit répondre vite et ne jamais lever : en cas de doute, null (le
     * tunnel bascule sur le fournisseur suivant, jamais bloqué).
     */
    public function quote(?string $city): ?float;
}
