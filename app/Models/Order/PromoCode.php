<?php

namespace App\Models\Order;

use App\Enums\Order\PromoCodeType;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Code promo (Phase 6C). Les règles d'application vivent dans PromoService ;
 * ce modèle ne porte que les données et quelques lectures simples.
 * `uses_count` n'est JAMAIS assigné depuis un formulaire : seul PromoService
 * l'incrémente (atomiquement) ou le décrémente (annulation).
 */
#[Fillable([
    'code', 'description', 'type', 'value', 'starts_at', 'ends_at',
    'min_subtotal', 'max_uses', 'once_per_customer', 'is_active', 'created_by',
])]
class PromoCode extends Model
{
    protected function casts(): array
    {
        return [
            'type' => PromoCodeType::class,
            'value' => 'decimal:2',
            'min_subtotal' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'once_per_customer' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Saisie insensible à la casse : on stocke et on compare en majuscules.
        static::saving(function (PromoCode $code): void {
            $code->code = self::normalize($code->code);
        });
    }

    public static function normalize(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promo_code_product');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'promo_code_category');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoCodeRedemption::class);
    }

    public function isTargeted(): bool
    {
        return $this->products()->exists() || $this->categories()->exists();
    }

    /** « -10 % » ou « -2 000 FCFA ». */
    public function valueLabel(): string
    {
        return $this->type === PromoCodeType::Percent
            ? '-'.rtrim(rtrim(number_format((float) $this->value, 2, ',', ''), '0'), ',').' %'
            : '-'.number_format((float) $this->value, 0, ',', ' ').' FCFA';
    }

    /** État lisible pour l'admin. */
    public function statusLabel(): string
    {
        return match (true) {
            ! $this->is_active => 'Désactivé',
            $this->starts_at?->isFuture() === true => 'Programmé',
            $this->ends_at?->isPast() === true => 'Expiré',
            $this->max_uses !== null && $this->uses_count >= $this->max_uses => 'Épuisé',
            default => 'Actif',
        };
    }
}
