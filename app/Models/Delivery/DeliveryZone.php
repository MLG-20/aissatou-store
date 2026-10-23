<?php

namespace App\Models\Delivery;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[Fillable(['name', 'city', 'fee', 'delay_label', 'is_active', 'position'])]
class DeliveryZone extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Même pattern que les catégories (Phase 2) : les zones changent
        // rarement mais sont lues à chaque tunnel — cache 1h, invalidé à
        // chaque écriture pour ne jamais servir un tarif périmé.
        static::saved(fn (): bool => Cache::forget('delivery_zones.active'));
        static::deleted(fn (): bool => Cache::forget('delivery_zones.active'));
    }

    /**
     * Zones actives ordonnées, en cache 1h (données publiques). On cache les
     * attributs bruts puis on réhydrate : `cache.serializable_classes` vaut
     * `false`, un modèle désérialisé deviendrait `__PHP_Incomplete_Class`
     * (même piège que la nav, cf. AppServiceProvider::navCategories).
     */
    public static function activeOrdered(): Collection
    {
        return static::hydrate(Cache::remember(
            'delivery_zones.active',
            3600,
            fn () => static::active()->orderBy('position')->get()
                ->map(fn (self $zone) => $zone->getAttributes())
                ->all()
        ));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Zone applicable à une ville : correspondance exacte (insensible à la
     * casse et aux accents), sinon la zone par défaut, sinon null (frais 0 —
     * le tunnel ne doit jamais bloquer faute de zone).
     */
    public static function forCity(?string $city): ?self
    {
        $wanted = self::normalise($city);

        $zones = static::activeOrdered();

        if ($wanted !== '') {
            $match = $zones->first(
                fn (self $zone) => self::normalise($zone->city) === $wanted
            );

            if ($match) {
                return $match;
            }
        }

        return $zones->firstWhere('city', null);
    }

    public static function normalise(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return trim(preg_replace('/\s+/', ' ', strtolower($ascii ?: $value)));
    }
}
