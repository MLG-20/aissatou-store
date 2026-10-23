<?php

namespace App\Models\Cart;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'session_id'])]
class Cart extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['abandoned_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // Un panier qui bouge (ligne ajoutée/modifiée → touch) n'est plus
        // abandonné. La détection, elle, écrit par requête directe (sans
        // événement), d'où pas de conflit.
        static::saving(function (Cart $cart): void {
            $cart->abandoned_at = null;
        });
    }

    /**
     * Panier abandonné (règle validée, Phase 6D) : non vide, inchangé depuis
     * 24 h, et pas de commande passée par son propriétaire depuis.
     */
    public function scopeAbandoned(Builder $query): Builder
    {
        return $query->whereNotNull('abandoned_at')->whereHas('items');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function total(): float
    {
        return $this->items->sum(fn (CartItem $item) => $item->lineTotal());
    }

    public function itemCount(): int
    {
        return $this->items->sum('quantity');
    }
}
