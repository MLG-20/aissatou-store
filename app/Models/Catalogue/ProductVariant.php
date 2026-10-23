<?php

namespace App\Models\Catalogue;

use App\Models\Content\SiteSetting;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['product_id', 'sku', 'attributes', 'price_override', 'stock', 'low_stock_threshold', 'is_active'])]
class ProductVariant extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'price_override' => 'decimal:2',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'low_stock_notified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Stock initial saisi à la création : premier mouvement du journal.
        static::created(function (ProductVariant $variant): void {
            if ($variant->stock > 0) {
                app(StockService::class)->recordInitial($variant);
            }
        });

        // Phase 6B : une fois créé, le stock ne change QUE par StockService
        // (vente, annulation, ajustement motivé) — toute autre écriture serait
        // une variation sans trace, donc on la refuse net.
        static::updating(function (ProductVariant $variant): void {
            if ($variant->isDirty('stock') && ! StockService::isWriting()) {
                throw new LogicException('Le stock ne se modifie que via StockService (mouvement journalisé).');
            }
        });

        // Référence de déclinaison attribuée automatiquement, sur le modèle de
        // la référence article : HIJ-001-01, HIJ-001-02… L'admin ne la saisit
        // jamais (cf. Product::generateSku pour la même logique côté produit).
        static::creating(function (ProductVariant $variant): void {
            if (blank($variant->sku)) {
                $variant->sku = static::generateSku($variant);
            }
        });
    }

    /** Prochaine référence libre au sein du produit parent : BASE-01, BASE-02… */
    private static function generateSku(ProductVariant $variant): string
    {
        // La déclinaison est créée après son produit (relation Filament,
        // seeder) : la référence article existe donc déjà. Repli sur ART-000
        // pour ne jamais générer une clé vide si le produit venait à manquer.
        $base = Product::withTrashed()->find($variant->product_id)?->sku ?? 'ART-000';

        $lastNumber = static::where('product_id', $variant->product_id)
            ->where('sku', 'like', $base.'-%')
            ->pluck('sku')
            ->map(fn (string $sku): int => (int) Str::afterLast($sku, '-'))
            ->max();

        return sprintf('%s-%02d', $base, ($lastNumber ?? 0) + 1);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('id');
    }

    /** Seuil d'alerte effectif : celui de la déclinaison, sinon le global. */
    public function lowStockThreshold(): int
    {
        return $this->low_stock_threshold ?? SiteSetting::current()->low_stock_threshold;
    }

    public function isLowStock(): bool
    {
        return $this->stock <= $this->lowStockThreshold();
    }

    /**
     * Déclinaisons au seuil ou en dessous (seuil propre ou global), pour le
     * badge et le filtre de l'inventaire — calculé en SQL, pas en PHP.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereRaw(
            'stock <= COALESCE(low_stock_threshold, ?)',
            [SiteSetting::current()->low_stock_threshold]
        );
    }

    /** « Col Claudine Brodé — Couleur : Bleu » pour journaux et alertes. */
    public function label(): string
    {
        $attributes = collect($this->getAttribute('attributes') ?? [])
            ->map(fn ($value, $key) => "{$key} : {$value}")
            ->implode(', ');

        return trim(($this->product?->name ?? 'Produit supprimé').($attributes ? ' — '.$attributes : ''));
    }

    /**
     * Noms de couleur reconnus, en teintes de la palette « Atelier Nocturne »
     * quand elles existent, sinon en équivalent sobre.
     *
     * Les caractéristiques d'une variante sont un `KeyValue` libre en admin
     * (« Couleur » => « Ivoire ») : rien ne garantit une valeur normalisée. Ce
     * dictionnaire couvre les noms courants ; pour tout le reste, la cliente
     * peut saisir directement un code hexadécimal comme valeur.
     *
     * @var array<string, string>
     */
    private const SWATCHES = [
        // Palette de la marque (rapport d'identité p.6).
        'encre' => '#17151B',
        'cassis' => '#4A1833',
        'parchemin' => '#F4E6D5',
        'sauge' => '#A7AE91',
        'garance' => '#9F2D40',
        // Noms courants du catalogue.
        'noir' => '#17151B',
        'blanc' => '#FFFFFF',
        'ivoire' => '#F3EADA',
        'creme' => '#F5EDE1',
        'ecru' => '#EFE5D5',
        'beige' => '#D9C7AE',
        'sable' => '#DCCBB2',
        'taupe' => '#8C7B6B',
        'camel' => '#B98A55',
        'marron' => '#5C4033',
        'bordeaux' => '#6B1F2E',
        'rouge' => '#9F2D40',
        'rose' => '#D9A5AC',
        'rose poudre' => '#E5C4C0',
        'violet' => '#5B3A63',
        'prune' => '#4A1833',
        'bleu' => '#27405C',
        'bleu nuit' => '#1B2A41',
        'bleu ciel' => '#A8C0D6',
        'turquoise' => '#3E8E8A',
        'vert' => '#3F5D45',
        'vert olive' => '#6B6A3C',
        'kaki' => '#77734F',
        'jaune' => '#D8AE4A',
        'moutarde' => '#C08A2E',
        'orange' => '#C2703D',
        'gris' => '#8A868C',
        'gris perle' => '#C9C6C9',
        'argent' => '#C0C0C4',
        'dore' => '#C0A062',
    ];

    /**
     * La valeur de couleur de la variante, quelle que soit la façon dont la
     * caractéristique a été nommée en admin.
     */
    public function colourValue(): ?string
    {
        // `getAttribute()` et non `$this->attributes` : à l'intérieur du modèle,
        // `$this->attributes` désigne le sac d'attributs bruts d'Eloquent, où la
        // colonne du même nom est encore une chaîne JSON. Seul `getAttribute()`
        // applique le cast `array` déclaré plus haut.
        foreach ((array) ($this->getAttribute('attributes') ?? []) as $key => $value) {
            if (in_array($this->normalise($key), ['couleur', 'color', 'coloris', 'teinte'], strict: true)) {
                return is_string($value) ? $value : null;
            }
        }

        return null;
    }

    /**
     * La pastille de couleur des cartes produit de la maquette (p.11).
     *
     * Renvoie `null` quand la couleur n'est pas reconnue : mieux vaut ne pas
     * afficher de pastille qu'en afficher une d'une teinte fausse — le rapport
     * interdit les couleurs hors palette (p.6) et, commercialement, une pastille
     * mensongère est pire que pas de pastille du tout.
     */
    public function swatch(): ?string
    {
        $value = $this->colourValue();

        if (blank($value)) {
            return null;
        }

        // Échappatoire : un code hexadécimal saisi directement en admin est
        // utilisé tel quel, sans passer par le dictionnaire.
        if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', trim($value)) === 1) {
            return strtoupper(trim($value));
        }

        return self::SWATCHES[$this->normalise($value)] ?? null;
    }

    /** Minuscules, sans accent ni espace superflu — « Rosé Poudré » → « rose poudre ». */
    private function normalise(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return trim(preg_replace('/\s+/', ' ', strtolower($ascii ?: $value)));
    }
}
