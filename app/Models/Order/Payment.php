<?php

namespace App\Models\Order;

use App\Enums\Order\PaymentEventType;
use App\Enums\Order\PaymentStatus;
use App\Services\Payment\PaymentManager;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id', 'provider', 'status', 'reference', 'amount',
    'token', 'method', 'confirmed_at', 'expires_at',
])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('id');
    }

    /**
     * Libellé du fournisseur via le registre des moyens de paiement : le
     * jour où Wave/Orange Money arrivent (phase 5), la facture affiche leur
     * nom sans changer de code ici.
     */
    public function providerLabel(): string
    {
        return app(PaymentManager::class)->find($this->provider)?->label() ?? $this->provider;
    }

    /** Payé en ligne (PayTech…) plutôt qu'à la remise. */
    public function isOnline(): bool
    {
        return (bool) app(PaymentManager::class)->find($this->provider)?->isOnline();
    }

    /** Ajoute une ligne au journal financier de ce paiement. */
    public function log(PaymentEventType $type, array $payload = [], ?string $ip = null): PaymentEvent
    {
        return $this->events()->create([
            'provider' => $this->provider,
            'type' => $type,
            'status' => $this->status->value,
            'payload' => $payload ?: null,
            'ip' => $ip,
        ]);
    }
}
