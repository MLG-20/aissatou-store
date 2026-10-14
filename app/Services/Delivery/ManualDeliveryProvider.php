<?php

namespace App\Services\Delivery;

use App\Models\Delivery\DeliveryZone;

/**
 * Mode manuel (Phase 4) : pas d'API, l'admin assigne et suit lui-même en
 * back-office (action « Organiser la livraison »). Le tarif vient des zones
 * gérées en admin — toujours disponible, c'est le filet de sécurité.
 */
class ManualDeliveryProvider implements DeliveryProvider
{
    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'Manuel (assigné en back-office)';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function quote(?string $city): ?float
    {
        $zone = DeliveryZone::forCity($city);

        return $zone ? (float) $zone->fee : 0;
    }
}
