<?php

namespace App\Models\Catalogue;

use App\Models\Concerns\HasFocalPoint;
use App\Services\ImageVariantGenerator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'description', 'image_path', 'position', 'is_active'])]
class Collection extends Model
{
    use HasFocalPoint;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Collection $collection): void {
            if (blank($collection->slug)) {
                $collection->slug = Str::slug($collection->name);
            }
        });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Même repli que les catégories : variante générée si présente, sinon
     * l'originale — jamais d'image cassée.
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
