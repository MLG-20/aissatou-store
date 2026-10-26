<?php

namespace App\Models\Catalogue;

use App\Models\Concerns\HasFocalPoint;
use App\Services\ImageVariantGenerator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'description', 'image_path', 'parent_id', 'position', 'is_active'])]
class Category extends Model
{
    use HasFactory;
    use HasFocalPoint;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            if (blank($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        // La nav de la boutique met les catégories racines en cache (voir
        // AppServiceProvider) — on invalide dès qu'une catégorie change,
        // pour ne jamais afficher une nav périmée après une modif admin.
        static::saved(fn () => Cache::forget('shop.nav_categories'));
        static::deleted(fn () => Cache::forget('shop.nav_categories'));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Préfixe de trois lettres pour les références article générées
     * automatiquement (cf. Product::generateSku) : « Hijabs » → HIJ,
     * « Sacs à main » → SAC.
     *
     * Sans accent (le SKU doit rester saisissable au clavier partout) et
     * complété par des X si le nom compte moins de trois lettres, pour que le
     * préfixe fasse toujours exactement trois caractères.
     */
    public function skuPrefix(): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $this->name) ?: $this->name;
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $ascii));

        return str_pad(substr($letters, 0, 3), 3, 'X');
    }

    /**
     * URL d'une variante redimensionnée de l'image de catégorie, ou null si
     * aucune image n'est définie (le composant d'affichage retombe alors sur
     * le visuel « signature » par défaut). Retombe sur l'original si la
     * variante demandée n'a pas encore été générée.
     */
    public function sizedUrl(string $size): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        $disk = Storage::disk('public');
        $sizedPath = app(ImageVariantGenerator::class)->sizedPath($this->image_path, $size);

        return $disk->exists($sizedPath) ? $disk->url($sizedPath) : $disk->url($this->image_path);
    }
}
