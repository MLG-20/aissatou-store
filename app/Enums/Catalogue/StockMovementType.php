<?php

namespace App\Enums\Catalogue;

/** Nature d'un mouvement de stock (Phase 6B) : liste fermée, lisible en audit. */
enum StockMovementType: string
{
    case Initial = 'initial';
    case Sale = 'vente';
    case Cancellation = 'annulation';
    case Adjustment = 'ajustement';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Stock initial',
            self::Sale => 'Vente',
            self::Cancellation => 'Remise en stock (annulation)',
            self::Adjustment => 'Ajustement manuel',
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
