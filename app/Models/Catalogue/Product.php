<?php

namespace App\Models\Catalogue;

use App\Enums\Catalogue\ProductLabel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['category_id', 'name', 'slug', 'sku', 'description', 'material_tagline', 'material_id', 'price', 'label', 'is_active'])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'label' => ProductLabel::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if (blank($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });

        // Référence article attribuée automatiquement à la création, jamais
        // saisie à la main : l'admin ne peut donc ni la laisser vide, ni créer
        // un doublon. Générée ici plutôt que dans le formulaire pour couvrir
        // aussi les seeders et les imports. Une valeur déjà présente (import,
        // reprise de données) est respectée.
        static::creating(function (Product $product): void {
            if (blank($product->sku)) {
                $product->sku = static::generateSku($product);
            }
        });
    }

    /**
     * Prochaine référence article libre pour la catégorie du produit :
     * PREFIXE-001, PREFIXE-002…
     */
    private static function generateSku(Product $product): string
    {
        $prefix = Category::find($product->category_id)?->skuPrefix() ?? 'ART';

        // Les produits soft-supprimés gardent leur référence (la ligne reste en
        // base) : on repart donc du plus grand numéro déjà utilisé, jamais d'un
        // simple comptage, pour ne jamais réattribuer une référence libérée.
        $lastNumber = static::withTrashed()
            ->where('sku', 'like', $prefix.'-%')
            ->pluck('sku')
            ->map(fn (string $sku): int => (int) Str::afterLast($sku, '-'))
            ->max();

        return sprintf('%s-%03d', $prefix, ($lastNumber ?? 0) + 1);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Les nouveautés de la boutique.
     *
     * DÉFINITION : est une nouveauté le produit que la cliente a explicitement
     * marqué « Nouveauté » en admin — rien d'autre.
     *
     * Ce scope existe parce que les trois endroits qui parlaient de nouveautés
     * disaient trois choses différentes, et aucune n'était la bonne :
     * · l'onglet « Nouveautés » pointait sur le catalogue complet, sans filtre ;
     * · la section « À découvrir » de l'accueil affichait `latest()->take(8)`,
     *   soit les dernières lignes créées — or tous les produits ayant été
     *   importés dans le même intervalle de quelques secondes, l'ordre était
     *   décidé par l'`id` et n'avait aucun sens commercial ;
     * · le label « Nouveauté » saisi en admin, lui, n'avait d'effet nulle part.
     *
     * Fonder la règle sur le label plutôt que sur `created_at` donne la main à
     * la cliente : une pièce reste une nouveauté tant qu'elle le décide, et
     * cesse de l'être quand elle retire le marquage. C'est aussi cohérent avec
     * le rapport d'identité (p.13), qui veut que les labels portent une
     * information qui « modifie réellement la décision ».
     */
    public function scopeNewArrivals(Builder $query): Builder
    {
        return $query->where('label', ProductLabel::New);
    }

    /**
     * Recherche/filtres combinables du catalogue public (section 2.1 du cahier
     * des charges). Toutes les valeurs sont déjà validées et nettoyées en amont
     * par FilterProductsRequest — ce scope ne fait qu'assembler la requête.
     *
     * @param  array{q: ?string, category_id: ?int, material_id: ?int, min_price: ?float, max_price: ?float, in_stock: bool, new_arrivals: bool}  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if ($filters['new_arrivals'] ?? false) {
            $query->newArrivals();
        }

        if (filled($filters['q'] ?? null)) {
            // Échappement des caractères spéciaux LIKE (%, _, \) pour qu'ils soient
            // traités comme du texte littéral et non comme des jokers de motif.
            $term = '%'.addcslashes($filters['q'], '%_\\').'%';

            $query->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', $term)
                    ->orWhereHas('category', fn (Builder $query) => $query->where('name', 'like', $term));
            });
        }

        if (filled($filters['category_id'] ?? null)) {
            $query->where('category_id', $filters['category_id']);
        }

        if (filled($filters['material_id'] ?? null)) {
            $query->where('material_id', $filters['material_id']);
        }

        if (filled($filters['min_price'] ?? null)) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (filled($filters['max_price'] ?? null)) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if ($filters['in_stock'] ?? false) {
            $query->where(function (Builder $query): void {
                $query->doesntHave('variants')
                    ->orWhereHas('variants', fn (Builder $query) => $query->active()->where('stock', '>', 0));
            });
        }

        return $query;
    }

    // Les deux méthodes ci-dessous lisent des relations déjà chargées (jamais
    // de requête) — l'appelant doit donc précharger images/variantes en amont
    // pour éviter le problème N+1 sur les listings.
    public function primaryImage(): ?ProductImage
    {
        return $this->images->firstWhere('is_primary', true) ?? $this->images->first();
    }

    public function isInStock(): bool
    {
        // Aucune variante = produit sans suivi de stock (le schéma n'enregistre
        // le stock qu'au niveau variante) — considéré comme toujours disponible.
        if ($this->variants->isEmpty()) {
            return true;
        }

        return $this->variants->contains(fn (ProductVariant $variant) => $variant->is_active && $variant->stock > 0);
    }
}
