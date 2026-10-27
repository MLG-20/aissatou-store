<?php

namespace App\Models\Order;

use App\Enums\Order\WhatsAppMessageStatus;
use App\Enums\Order\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal d'un message WhatsApp envoyé pour une commande. Ne contient ni le
 * jeton ni le texte complet : le modèle et l'état suffisent au suivi.
 */
#[Fillable(['order_id', 'to', 'template', 'wamid', 'status', 'error'])]
class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected function casts(): array
    {
        return [
            'template' => WhatsAppTemplate::class,
            'status' => WhatsAppMessageStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
