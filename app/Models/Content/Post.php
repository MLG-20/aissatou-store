<?php

namespace App\Models\Content;

use App\Models\Catalogue\Product;
use App\Models\Concerns\HasFocalPoint;
use App\Services\ImageVariantGenerator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

#[Fillable([
    'title', 'slug', 'excerpt', 'content', 'cover_image_path',
    'is_published', 'published_at', 'position',
])]
class Post extends Model
{
    use HasFocalPoint;

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

    /**
     * Couverture affichée : la photo de l'article, sinon celle du premier
     * produit lié (c'est de lui que parle l'article). Null = couverture
     * typographique côté vue. Charger `products.images` pour éviter le N+1.
     */
    public function coverUrl(string $size): ?string
    {
        return $this->sizedUrl($size)
            ?? $this->products->first(fn (Product $product) => $product->primaryImage())?->primaryImage()->sizedUrl($size);
    }

    /** Temps de lecture estimé, à 200 mots par minute (au moins 1 min). */
    public function readingMinutes(): int
    {
        $words = preg_match_all('/[\p{L}\p{N}’\']+/u', strip_tags(($this->excerpt ?? '').' '.($this->content ?? '')));

        return max(1, (int) ceil($words / 200));
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
     *
     * Pas `strip_tags` : il garde les attributs des balises autorisées
     * (`<p onmouseover=…>`, `<a href="javascript:…">`). Le sanitizer ne
     * garde que `href` sur les liens, en http(s) ou mailto.
     */
    public function safeContent(): string
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('a', ['href'])
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow');

        foreach (['p', 'br', 'strong', 'em', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'blockquote'] as $element) {
            $config = $config->allowElement($element);
        }

        return (new HtmlSanitizer($config))->sanitize($this->content ?? '');
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
