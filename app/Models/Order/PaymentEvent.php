<?php

namespace App\Models\Order;

use App\Enums\Order\PaymentEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Journal financier immuable (Phase 5, traçabilité) : on ajoute, on ne
 * réécrit jamais. Une ligne modifiée ou supprimée ne vaudrait plus rien
 * devant un litige.
 */
#[Fillable(['payment_id', 'provider', 'type', 'status', 'payload', 'ip'])]
class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => PaymentEventType::class,
            'payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal des paiements est immuable.'));
        static::deleting(fn () => throw new LogicException('Le journal des paiements est immuable.'));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
