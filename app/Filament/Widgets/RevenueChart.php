<?php

namespace App\Filament\Widgets;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\WidgetRequiresAdminPermission;
use App\Services\StatisticsService;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/** Chiffre d'affaires encaissé jour par jour, 30 derniers jours (6D). */
class RevenueChart extends ChartWidget
{
    use WidgetRequiresAdminPermission;

    protected static ?int $sort = 2;

    protected static ?string $heading = 'Chiffre d\'affaires — 30 derniers jours';

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = 'full';

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Statistics;
    }

    protected function getData(): array
    {
        $series = app(StatisticsService::class)->dailyRevenue(30);

        return [
            'datasets' => [[
                'label' => 'CA (FCFA)',
                'data' => array_values($series),
                'borderColor' => '#9F2D40',
                'backgroundColor' => 'rgba(159, 45, 64, 0.12)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => array_map(fn (string $day): string => CarbonImmutable::parse($day)->format('d/m'), array_keys($series)),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
