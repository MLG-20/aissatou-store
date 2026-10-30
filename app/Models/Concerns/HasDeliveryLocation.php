<?php

namespace App\Models\Concerns;

/**
 * Position exacte de livraison (adresse du carnet et commande) : point GPS
 * ou lien Maps collé par la cliente. Le point GPS prime : il est précis et
 * s'ouvre dans n'importe quelle application de navigation.
 */
trait HasDeliveryLocation
{
    public const LOCATION_FIELDS = ['latitude', 'longitude', 'location_accuracy', 'maps_link'];

    public function initializeHasDeliveryLocation(): void
    {
        $this->mergeCasts([
            'latitude' => 'float',
            'longitude' => 'float',
            'location_accuracy' => 'integer',
        ]);
    }

    public function hasLocation(): bool
    {
        return $this->locationUrl() !== null;
    }

    /** Lien d'itinéraire pour le livreur ou l'admin. */
    public function locationUrl(): ?string
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return 'https://www.google.com/maps/search/?api=1&query='.$this->latitude.','.$this->longitude;
        }

        return $this->maps_link ?: null;
    }

    /** @return array<string, mixed> Pour recopier la position d'un modèle à l'autre. */
    public function locationAttributes(): array
    {
        return collect(self::LOCATION_FIELDS)->mapWithKeys(fn (string $field) => [$field => $this->{$field}])->all();
    }
}
