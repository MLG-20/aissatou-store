<?php

namespace App\Models\Catalogue;

use App\Enums\Catalogue\StockMovementType;
use App\Models\Admin;
use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Journal des mouvements de stock (Phase 6B), immuable : chaque ligne dit
 * qui, quand, combien, pourquoi et le stock qui en a résulté.
 */
#[Fillable([
    'product_variant_id', 'sku', 'label', 'type', 'quantity', 'stock_after',
    'reason', 'admin_id', 'admin_name', 'order_id',
])]
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'integer',
            'stock_after' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal des mouvements de stock est immuable.'));
        static::deleting(fn () => throw new LogicException('Le journal des mouvements de stock est immuable.'));
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
