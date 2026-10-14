<?php

namespace App\Models\Order;

use App\Enums\Order\PaymentStatus;
use App\Services\Payment\PaymentManager;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'provider', 'status', 'reference', 'amount'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
}
