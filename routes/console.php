<?php

use App\Models\Admin;
use App\Services\Admin\TwoFactor;
use App\Services\AdminAudit;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Téléphone et codes de secours perdus, sans autre admin pour débloquer le compte.
Artisan::command('admin:reset-two-factor {email}', function (string $email, TwoFactor $twoFactor) {
    $admin = Admin::query()->where('email', $email)->first();

    if (! $admin) {
        $this->error('Aucun compte admin avec cette adresse.');

        return 1;
    }

    $twoFactor->reset($admin);
    AdminAudit::record('admin.two_factor_reset', $admin, ['par' => 'commande serveur'], $admin);
    $this->info('Double authentification réinitialisée : mise en place demandée à la prochaine connexion.');
})->purpose("Réinitialise la double authentification d'un compte admin");

// Phase 5 : commandes PayTech impayées après le délai → annulées, stock
// remis en vente. Nécessite `schedule:run` en cron (prod) ou `schedule:work`.
Schedule::command('payments:expire')->everyFiveMinutes()->withoutOverlapping();

// Phase 6D : agrégats du tableau de bord (nuit) et paniers abandonnés.
Schedule::command('stats:aggregate')->dailyAt('00:15')->withoutOverlapping();
Schedule::command('carts:detect-abandoned')->hourly()->withoutOverlapping();

// Jetons API expirés (30 jours) : retirés de la base.
Schedule::command('sanctum:prune-expired')->daily()->withoutOverlapping();
