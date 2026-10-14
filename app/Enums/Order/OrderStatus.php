<?php

namespace App\Enums\Order;

/**
 * Cycle de vie explicite de la commande (phase 3) : statuts fermés + transitions
 * autorisées documentées, pas de texte libre qui dériverait avec le temps.
 */
enum OrderStatus: string
{
    case New = 'nouvelle';
    case Preparing = 'preparation';
    case Shipped = 'expediee';
    case Delivered = 'livree';
    case Cancelled = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nouvelle',
            self::Preparing => 'En préparation',
            self::Shipped => 'Expédiée',
            self::Delivered => 'Livrée',
            self::Cancelled => 'Annulée',
        };
    }

    /** Transitions autorisées depuis chaque statut. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), strict: true);
    }

    /** @return array<string, string> Pour les listes déroulantes de l'admin. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
