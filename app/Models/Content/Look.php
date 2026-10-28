<?php

namespace App\Models\Content;

use App\Services\ImageVariantGenerator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * « Le Look » de l'accueil : une photo ou une vidéo (30 s au plus) de mise
 * en situation, et les pièces portées (LookHotspot).
 *
 * Vidéo : `video_source_path` est le fichier envoyé, `video_path` sa version
 * web (ProcessLookVideo), `image_path` l'image d'aperçu qui en est tirée.
 */
#[Fillable(['title', 'subtitle', 'media_type', 'image_path', 'video_source_path', 'video_path', 'video_status', 'video_error', 'position', 'is_active'])]
class Look extends Model
{
    public const VIDEO_PROCESSING = 'processing';

    public const VIDEO_READY = 'ready';

    public const VIDEO_FAILED = 'failed';

    protected $attributes = [
        'media_type' => 'image',
    ];

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

    /** Affichable : une photo, ou une vidéo dont la version web est prête. */
    public function scopeDisplayable(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where('media_type', 'image')->whereNotNull('image_path')
            ->orWhere(fn (Builder $query) => $query->where('media_type', 'video')->where('video_status', self::VIDEO_READY)));
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }

    public function videoUrl(): ?string
    {
        return $this->video_path ? Storage::disk('public')->url($this->video_path) : null;
    }

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
