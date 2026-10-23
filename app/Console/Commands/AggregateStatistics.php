<?php

namespace App\Console\Commands;

use App\Services\StatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Agrégats quotidiens du tableau de bord (Phase 6D). Planifiée chaque nuit
 * sur les 7 derniers jours (absorbe une confirmation de paiement tardive) ;
 * `--from=AAAA-MM-JJ` pour un rattrapage complet. Purge ensuite les
 * empreintes de visiteurs de plus de 35 jours (seul le total est gardé).
 */
class AggregateStatistics extends Command
{
    protected $signature = 'stats:aggregate {--days=7 : Nombre de jours passés à recalculer} {--from= : Recalculer depuis cette date}';

    protected $description = 'Calcule les agrégats quotidiens des statistiques';

    public function handle(StatisticsService $statistics): int
    {
        $yesterday = CarbonImmutable::yesterday();
        $from = $this->option('from')
            ? CarbonImmutable::parse($this->option('from'))->startOfDay()
            : $yesterday->subDays(max(1, (int) $this->option('days')) - 1);

        $count = 0;
        for ($day = $from; $day->lte($yesterday); $day = $day->addDay()) {
            $statistics->aggregateDay($day);
            $count++;
        }

        DB::table('daily_visitors')->where('day', '<', now()->subDays(35)->toDateString())->delete();

        $this->info("{$count} jour(s) agrégé(s).");

        return self::SUCCESS;
    }
}
