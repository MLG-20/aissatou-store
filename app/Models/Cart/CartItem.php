<?php

namespace App\Models\Cart;

use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cart_id', 'product_id', 'product_variant_id', 'quantity'])]
class CartItem extends Model
{
    use HasFactory;

    /**
     * Toute modification d'une ligne date le panier ET le sort de l'état
     * « abandonné » (Phase 6D) : c'est `carts.updated_at` qui dit depuis
     * quand un panier est inactif. (Pas `$touches` : Laravel y fait une
     * mise à jour brute, sans remettre `abandoned_at` à null.)
     */
    protected static function booted(): void
    {
        $refresh = fn (CartItem $item) => Cart::whereKey($item->cart_id)
            ->update(['updated_at' => now(), 'abandoned_at' => null]);

        static::saved($refresh);
        static::deleted($refresh);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    // Jamais stocké : le panier doit toujours refléter le prix actuel du
    // catalogue, pas un instantané (le figeage du prix n'arrive qu'à la
    // commande, Phase 3).
    public function unitPrice(): float
    {
        return (float) ($this->variant?->price_override ?? $this->product->price);
    }

    public function lineTotal(): float
    {
        return $this->unitPrice() * $this->quantity;
    }

    public function isAvailable(): bool
    {
        if (! $this->product->is_active) {
            return false;
        }

        if ($this->variant && (! $this->variant->is_active || $this->variant->stock < $this->quantity)) {
            return false;
        }

        return true;
    }
}
