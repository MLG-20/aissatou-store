<?php

namespace App\Filament\Resources\Catalogue\InventoryResource\Widgets;

use App\Enums\Admin\AdminPermission;
use App\Models\Admin;
use App\Models\Content\SiteSetting;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Vue d'ensemble de l'inventaire par catégorie (Phase 6B) : unités en stock,
 * déclinaisons suivies, en stock faible et en rupture — une seule requête
 * agrégée, pas de boucle PHP sur les déclinaisons.
 */
class StockByCategory extends Widget
{
    protected static string $view = 'filament.widgets.stock-by-category';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $admin = filament()->auth()->user();

        return $admin instanceof Admin && $admin->hasAdminPermission(AdminPermission::Stock);
    }

    /** @return list<object{category: string, variants: int, units: int, low: int, out: int}> */
    public function rows(): array
    {
        $threshold = SiteSetting::current()->low_stock_threshold;

        return DB::table('product_variants as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('v.is_active', true)
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->groupBy('c.id', 'c.name')
            ->orderBy('c.name')
            ->selectRaw('COALESCE(c.name, ?) as category', ['Sans catégorie'])
            ->selectRaw('COUNT(*) as variants')
            ->selectRaw('SUM(v.stock) as units')
            ->selectRaw('SUM(CASE WHEN v.stock > 0 AND v.stock <= COALESCE(v.low_stock_threshold, ?) THEN 1 ELSE 0 END) as low', [$threshold])
            ->selectRaw('SUM(CASE WHEN v.stock = 0 THEN 1 ELSE 0 END) as `out`')
            ->get()
            ->all();
    }
}
