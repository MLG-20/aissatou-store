<?php

namespace App\Enums\Delivery;

/**
 * Livreur de la boutique, ou service extérieur (Yango, Tiak-Tiak…) dont le
 * chauffeur peut changer d'une course à l'autre.
 */
enum CourierType: string
{
    case Internal = 'interne';
    case External = 'externe';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Livreur de la boutique',
            self::External => 'Service extérieur',
        };
    }

    /** @return array<string, string> Pour les listes déroulantes de l'admin. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
