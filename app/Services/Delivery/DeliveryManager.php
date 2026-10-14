<?php

namespace App\Services\Delivery;

/**
 * Registre des transporteurs : le premier disponible dans l'ordre de la
 * liste chiffre la livraison. Aujourd'hui : Yango s'il est configuré, sinon
 * le manuel — le tunnel n'a pas à changer quand Yango arrivera.
 */
class DeliveryManager
{
    /** @return list<DeliveryProvider> */
    public function available(): array
    {
        return collect($this->providers())
            ->filter(fn (DeliveryProvider $provider) => $provider->isAvailable())
            ->values()
            ->all();
    }

    /** Fournisseur prioritaire : le premier disponible. */
    public function primary(): DeliveryProvider
    {
        return $this->available()[0] ?? app(ManualDeliveryProvider::class);
    }

    /** @return list<DeliveryProvider> */
    private function providers(): array
    {
        return [
            app(YangoDeliveryProvider::class),
            app(ManualDeliveryProvider::class),
        ];
    }
}
