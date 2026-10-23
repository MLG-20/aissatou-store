<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Compteur de visiteurs uniques par jour, maison et anonyme (Phase 6D,
 * décision Ahmed) : pas de Google Analytics, pas de cookie supplémentaire,
 * aucune IP stockée.
 *
 * Empreinte = sha256(sel du jour | IP | navigateur). Le sel est aléatoire,
 * propre à chaque jour, gardé seulement en cache 2 jours puis perdu : une
 * empreinte ne permet ni de retrouver une IP, ni de relier deux jours.
 */
class VisitTracker
{
    /** Robots, aperçus de liens et outils en ligne de commande. */
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|preview|curl|wget|python|httpclient|headless|lighthouse|monitor/i';

    public function shouldCount(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        return $request->isMethod('GET')
            && ! $request->ajax()
            && ! $request->expectsJson()
            && $request->header('Purpose') !== 'prefetch'
            && ! $request->is('admin', 'admin/*', 'livewire/*', 'api/*', 'up')
            && $agent !== ''
            && ! preg_match(self::BOT_PATTERN, $agent)
            // L'équipe qui consulte la boutique ne gonfle pas les chiffres.
            && ! Auth::guard('admin')->check();
    }

    public function record(Request $request): void
    {
        $day = now()->toDateString();
        $salt = Cache::remember('visits.salt.'.$day, now()->addDays(2), fn (): string => bin2hex(random_bytes(16)));

        DB::table('daily_visitors')->insertOrIgnore([
            'day' => $day,
            'fingerprint' => hash('sha256', $salt.'|'.$request->ip().'|'.$request->userAgent()),
        ]);
    }
}
