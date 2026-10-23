<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Périodes du tableau de bord (Phase 6D) : jour, semaine, mois, toujours
 * comparées à la période précédente de même durée.
 */
class StatsPeriod
{
    public const DEFAULT = 'week';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            'day' => 'Aujourd\'hui (vs hier)',
            'week' => '7 derniers jours (vs 7 précédents)',
            'month' => '30 derniers jours (vs 30 précédents)',
        ];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} [début, fin] inclus */
    public static function range(?string $period): array
    {
        $today = CarbonImmutable::today();

        return match ($period) {
            'day' => [$today, $today],
            'month' => [$today->subDays(29), $today],
            default => [$today->subDays(6), $today],
        };
    }
}
