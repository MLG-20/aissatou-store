<?php

namespace App\Enums\Order;

/**
 * État d'un message WhatsApp. Les valeurs reprennent celles de Meta
 * (webhook `statuses[].status`), sauf `queued` : en file, pas encore accepté.
 */
enum WhatsAppMessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'En attente',
            self::Sent => 'Envoyé',
            self::Delivered => 'Reçu',
            self::Read => 'Lu',
            self::Failed => 'Échec',
        };
    }

    /**
     * Ordre d'avancement : un statut n'en remplace qu'un plus ancien (Meta
     * peut livrer « distribué » après « lu »). L'échec est définitif.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Queued => 0,
            self::Sent => 1,
            self::Delivered => 2,
            self::Read => 3,
            self::Failed => 4,
        };
    }
}
