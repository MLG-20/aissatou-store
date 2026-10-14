<?php

namespace App\Models\Content;

use App\Models\Catalogue\Product;
use App\Services\ImageVariantGenerator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'title', 'slug', 'excerpt', 'content', 'cover_image_path',
    'is_published', 'published_at', 'position',
])]
class Post extends Model
{
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Post $post): void {
            if (blank($post->slug)) {
                $post->slug = Str::slug($post->title);
            }
        });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withTimestamps();
    }

    /** Visible publiquement : publié ET date de parution atteinte. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Corps de l'article assaini : l'éditeur riche stocke du HTML, on ne
     * garde qu'une liste fermée de balises de mise en forme — aucun script,
     * même si le contenu venait d'une source compromise. C'est ce qui permet
     * d'afficher avec `{!! !!}` sans ouvrir de faille XSS persistante.
     */
    public function safeContent(): string
    {
        return strip_tags(
            $this->content ?? '',
            '<p><br><strong><em><u><a><ul><ol><li><h2><h3><blockquote>'
        );
    }

    public function sizedUrl(string $size): ?string
    {
        if (! $this->cover_image_path) {
            return null;
        }

        $disk = Storage::disk('public');
        $sizedPath = app(ImageVariantGenerator::class)->sizedPath($this->cover_image_path, $size);

        return $disk->exists($sizedPath) ? $disk->url($sizedPath) : $disk->url($this->cover_image_path);
    }
}
