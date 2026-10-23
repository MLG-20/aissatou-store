<?php

namespace App\Filament\Widgets;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\WidgetRequiresAdminPermission;
use App\Services\StatisticsService;
use App\Support\StatsPeriod;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Meilleures ventes de la période (6D), en quantité et en chiffre
 * d'affaires, d'après les commandes payées uniquement.
 */
class TopProducts extends Widget
{
    use InteractsWithPageFilters;
    use WidgetRequiresAdminPermission;

    protected static ?int $sort = 3;

    protected static string $view = 'filament.widgets.top-products';

    protected int|string|array $columnSpan = 'full';

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Statistics;
    }

    /** @return array{quantity: list<object>, revenue: list<object>} */
    public function rankings(): array
    {
        [$from, $to] = StatsPeriod::range($this->filters['period'] ?? null);
        $statistics = app(StatisticsService::class);

        return [
            'quantity' => $statistics->topProducts($from, $to, 'quantity', 5),
            'revenue' => $statistics->topProducts($from, $to, 'revenue', 5),
        ];
    }
}
