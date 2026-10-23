<?php

namespace App\Enums\Order;

enum PromoCodeType: string
{
    case Percent = 'pourcentage';
    case Fixed = 'montant';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Pourcentage',
            self::Fixed => 'Montant fixe (FCFA)',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
