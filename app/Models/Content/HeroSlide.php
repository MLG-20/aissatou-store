<?php

namespace App\Models\Content;

use App\Models\Concerns\HasFocalPoint;
use App\Services\ImageVariantGenerator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['image_path', 'mobile_image_path', 'title', 'subtitle', 'cta_label', 'cta_url', 'position', 'is_active'])]
class HeroSlide extends Model
{
    use HasFactory;
    use HasFocalPoint;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function sizedUrl(string $size): ?string
    {
        return $this->sizedUrlOf($this->image_path, $size);
    }

    /** Version téléphone (portrait) ; null si aucune n'est fournie. */
    public function mobileSizedUrl(string $size): ?string
    {
        return $this->sizedUrlOf($this->mobile_image_path, $size);
    }

    private function sizedUrlOf(?string $path, string $size): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');
        $sizedPath = app(ImageVariantGenerator::class)->sizedPath($path, $size);

        return $disk->exists($sizedPath) ? $disk->url($sizedPath) : $disk->url($path);
    }
}
