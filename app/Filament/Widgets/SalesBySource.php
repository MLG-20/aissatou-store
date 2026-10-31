<?php

namespace App\Filament\Widgets;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\WidgetRequiresAdminPermission;
use App\Services\StatisticsService;
use App\Support\StatsPeriod;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Ventes par source de la période (Visibilité V4) : d'où viennent les
 * visites, les commandes et le chiffre d'affaires.
 */
class SalesBySource extends Widget
{
    use InteractsWithPageFilters;
    use WidgetRequiresAdminPermission;

    protected static ?int $sort = 4;

    protected static string $view = 'filament.widgets.sales-by-source';

    protected int|string|array $columnSpan = 'full';

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Statistics;
    }

    /** @return list<object> */
    public function rows(): array
    {
        [$from, $to] = StatsPeriod::range($this->filters['period'] ?? null);

        return app(StatisticsService::class)->salesBySource($from, $to);
    }
}
