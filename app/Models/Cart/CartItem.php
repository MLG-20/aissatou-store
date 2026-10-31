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
        return $this->unavailableReason() === null;
    }

    /**
     * Ce qui empêche de commander cette ligne, dit à la cliente, ou null si
     * tout va bien. Mêmes règles que OrderService::createFromCart, qui reste
     * seul juge au moment de valider.
     */
    public function unavailableReason(): ?string
    {
        if (! $this->product->is_active || ($this->variant && ! $this->variant->is_active)) {
            return 'Cet article n\'est plus disponible : retirez-le pour continuer.';
        }

        if (! $this->variant) {
            return $this->product->variants()->exists()
                ? 'Cette déclinaison n\'est plus proposée : retirez cet article pour en choisir une autre.'
                : null;
        }

        if ($this->variant->stock <= 0) {
            return 'Épuisé : retirez cet article pour continuer.';
        }

        if ($this->variant->stock < $this->quantity) {
            return 'Plus que '.$this->variant->stock.' en stock : ajustez la quantité pour continuer.';
        }

        return null;
    }
}
