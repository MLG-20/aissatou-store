<?php

namespace App\Models\Content;

use App\Services\ImageVariantGenerator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * « Le Look » de l'accueil : une photo de mise en situation dont les points
 * (LookHotspot) mènent aux pièces portées.
 */
#[Fillable(['title', 'subtitle', 'image_path', 'position', 'is_active'])]
class Look extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function hotspots(): HasMany
    {
        return $this->hasMany(LookHotspot::class)->orderBy('position');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function sizedUrl(string $size): ?string
    {
        $disk = Storage::disk('public');
        $sizedPath = app(ImageVariantGenerator::class)->sizedPath($this->image_path, $size);

        return $disk->exists($sizedPath) ? $disk->url($sizedPath) : $disk->url($this->image_path);
    }
}
