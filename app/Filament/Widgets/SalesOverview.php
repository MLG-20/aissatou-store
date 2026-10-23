<?php

namespace App\Filament\Widgets;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\WidgetRequiresAdminPermission;
use App\Services\StatisticsService;
use App\Support\StatsPeriod;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Chiffres clés de la période, comparés à la période précédente (6D). */
class SalesOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use WidgetRequiresAdminPermission;

    protected static ?int $sort = 1;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Statistics;
    }

    protected function getStats(): array
    {
        $statistics = app(StatisticsService::class);
        [$from, $to] = StatsPeriod::range($this->filters['period'] ?? null);
        ['current' => $now, 'previous' => $before] = $statistics->summary($from, $to);
        $abandoned = $statistics->abandonedCarts();

        return [
            $this->compare(
                Stat::make('Chiffre d\'affaires', $this->money($now['revenue'])),
                $now['revenue'],
                $before['revenue'],
                'Commandes payées, livraison incluse — dont marchandises : '.$this->money($now['merchandise'])
            ),
            $this->compare(Stat::make('Commandes payées', (string) $now['paid_orders']), $now['paid_orders'], $before['paid_orders']),
            $this->compare(Stat::make('Panier moyen', $this->money($now['average_basket'])), $now['average_basket'], $before['average_basket']),
            $this->compare(
                Stat::make('Taux de conversion', number_format($now['conversion'], 1, ',', ' ').' %'),
                $now['conversion'],
                $before['conversion'],
                $now['placed_orders'].' commande(s) pour '.$now['visitors'].' visiteur(s) unique(s)'
            ),
            Stat::make('Paniers abandonnés', (string) $abandoned['count'])
                ->description($this->money($abandoned['value']).' d\'articles — '.$abandoned['customers'].' client(e)s identifié(e)s')
                ->color($abandoned['count'] > 0 ? 'warning' : 'gray'),
        ];
    }

    private function compare(Stat $stat, float|int $current, float|int $previous, ?string $note = null): Stat
    {
        if ($previous == 0) {
            $trend = $current > 0 ? 'nouveau (rien sur la période précédente)' : 'stable';
            $color = $current > 0 ? 'success' : 'gray';
            $icon = null;
        } else {
            $change = ($current - $previous) / $previous * 100;
            $trend = ($change >= 0 ? '+' : '').number_format($change, 1, ',', ' ').' % vs période précédente';
            $color = $change >= 0 ? 'success' : 'danger';
            $icon = $change >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        }

        return $stat
            ->description($note ? $trend.' · '.$note : $trend)
            ->descriptionIcon($icon)
            ->color($color);
    }

    private function money(float|int $amount): string
    {
        return number_format((float) $amount, 0, ',', ' ').' FCFA';
    }
}
