<?php

namespace App\Enums\Order;

/**
 * Statut du paiement, volontairement distinct du statut de commande : une
 * commande « en préparation » peut avoir un paiement « en attente » (cas du
 * paiement à la livraison, confirmé seulement à la remise).
 */
enum PaymentStatus: string
{
    case Pending = 'en_attente';
    case Confirmed = 'confirme';
    case Failed = 'echoue';
    case Refunded = 'rembourse';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Confirmed => 'Confirmé',
            self::Failed => 'Échoué',
            self::Refunded => 'Remboursé',
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
