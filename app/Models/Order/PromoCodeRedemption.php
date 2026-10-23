<?php

namespace App\Models\Order;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Utilisation d'un code promo par une commande (rendue si annulée). */
#[Fillable(['promo_code_id', 'order_id', 'user_id', 'discount', 'released_at'])]
class PromoCodeRedemption extends Model
{
    protected function casts(): array
    {
        return [
            'discount' => 'decimal:2',
            'released_at' => 'datetime',
        ];
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
