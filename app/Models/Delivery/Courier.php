<?php

namespace App\Models\Delivery;

use App\Enums\Delivery\CourierType;
use App\Enums\Order\OrderStatus;
use App\Models\Order\Order;
use Database\Factories\Delivery\CourierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'type', 'is_active'])]
#[Hidden(['tour_token_hash'])]
class Courier extends Model
{
    /** @use HasFactory<CourierFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => CourierType::class,
            'is_active' => 'boolean',
            'tour_token_issued_at' => 'datetime',
        ];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Commandes à préparer ou en route : sa tournée. */
    public function ordersInProgress(): HasMany
    {
        return $this->orders()->whereIn('status', [OrderStatus::Preparing, OrderStatus::Shipped]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
