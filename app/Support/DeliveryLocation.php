<?php

namespace App\Support;

use App\Rules\MapsLink;

/**
 * Règles de validation de la position de livraison, partagées entre le
 * carnet d'adresses et le tunnel de commande.
 */
class DeliveryLocation
{
    /**
     * Cadre large autour du Sénégal : refuse la position d'une cliente qui
     * commande depuis l'étranger pour un proche resté au pays.
     */
    public const LATITUDE = [12.0, 16.8];

    public const LONGITUDE = [-17.6, -11.3];

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:'.implode(',', self::LATITUDE)],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:'.implode(',', self::LONGITUDE)],
            'location_accuracy' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'maps_link' => ['nullable', 'string', 'max:500', new MapsLink],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        $outside = 'Cette position est hors du Sénégal. Partagez-la depuis le lieu de livraison, ou retirez-la.';

        return [
            'latitude.between' => $outside,
            'longitude.between' => $outside,
        ];
    }
}
