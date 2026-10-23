<?php

namespace App\Enums\Order;

/**
 * Nature d'une ligne du journal des paiements (Phase 5) : liste fermée, pour
 * qu'un audit ou un litige se lise sans interpréter du texte libre.
 */
enum PaymentEventType: string
{
    case Initiated = 'initie';
    case InitiationFailed = 'initiation_echouee';
    case Notification = 'notification';
    case SuspiciousNotification = 'notification_suspecte';
    case StatusChecked = 'verification';
    case Confirmed = 'confirme';
    case Failed = 'echoue';
    case Expired = 'expire';

    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Paiement initié',
            self::InitiationFailed => 'Échec d\'initiation',
            self::Notification => 'Notification reçue',
            self::SuspiciousNotification => 'Notification suspecte rejetée',
            self::StatusChecked => 'Vérification auprès du fournisseur',
            self::Confirmed => 'Paiement confirmé',
            self::Failed => 'Paiement échoué',
            self::Expired => 'Délai dépassé, commande annulée',
        };
    }
}
