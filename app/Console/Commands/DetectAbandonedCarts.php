<?php

namespace App\Console\Commands;

use App\Services\StatisticsService;
use Illuminate\Console\Command;

/** Marque les paniers abandonnés (Phase 6D), planifiée toutes les heures. */
class DetectAbandonedCarts extends Command
{
    protected $signature = 'carts:detect-abandoned';

    protected $description = 'Marque les paniers inactifs depuis 24 h sans commande comme abandonnés';

    public function handle(StatisticsService $statistics): int
    {
        $this->info($statistics->detectAbandonedCarts().' panier(s) marqué(s) abandonné(s).');

        return self::SUCCESS;
    }
}
