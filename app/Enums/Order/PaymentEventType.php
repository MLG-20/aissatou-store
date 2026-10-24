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

    /** Ce qui s'est passé, lisible par la gérante sans connaître PayTech. */
    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Client envoyé vers la page de paiement',
            self::InitiationFailed => 'Ouverture du paiement impossible',
            self::Notification => 'Message reçu de PayTech',
            self::SuspiciousNotification => 'Message suspect bloqué',
            self::StatusChecked => 'Vérification auprès de PayTech',
            self::Confirmed => 'Paiement reçu',
            self::Failed => 'Paiement annulé ou refusé',
            self::Expired => 'Délai dépassé, commande annulée',
        };
    }

    /** Explication affichée sur la fiche de l'événement. */
    public function description(): string
    {
        return match ($this) {
            self::Initiated => 'Le client a validé sa commande et a été redirigé vers PayTech pour payer.',
            self::InitiationFailed => 'PayTech n\'a pas pu ouvrir la page de paiement (service injoignable ou demande refusée). Le client peut réessayer.',
            self::Notification => 'PayTech nous a envoyé le résultat du paiement. Il est contrôlé avant d\'être pris en compte.',
            self::SuspiciousNotification => 'Un message se faisant passer pour PayTech a été rejeté (signature, commande ou montant incorrect). Aucune commande n\'a été modifiée. Si cela se répète, prévenez le responsable technique.',
            self::StatusChecked => 'La boutique a demandé directement à PayTech où en est le paiement.',
            self::Confirmed => 'L\'argent est bien arrivé : la commande est payée.',
            self::Failed => 'Le client a annulé ou le paiement n\'a pas abouti. La commande reste impayée.',
            self::Expired => 'Le client n\'a pas payé dans le délai : la commande a été annulée automatiquement.',
        };
    }

    /** Couleur du badge dans le back-office. */
    public function color(): string
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::Failed, self::InitiationFailed, self::Expired => 'warning',
            self::SuspiciousNotification => 'danger',
            default => 'gray',
        };
    }
}
