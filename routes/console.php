<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 5 : commandes PayTech impayées après le délai → annulées, stock
// remis en vente. Nécessite `schedule:run` en cron (prod) ou `schedule:work`.
Schedule::command('payments:expire')->everyFiveMinutes()->withoutOverlapping();
