<?php

namespace App\Http\Middleware;

use App\Services\Admin\TwoFactor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le mot de passe seul n'ouvre pas le back-office : mise en place obligatoire
 * la première fois, puis code à chaque nouvelle session. Aussi rejoué sur les
 * requêtes Livewire (middleware persistant du panneau).
 */
class EnsureAdminTwoFactor
{
    public function __construct(private TwoFactor $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin || $request->routeIs('filament.admin.two-factor.*', 'filament.admin.auth.logout')) {
            return $next($request);
        }

        if ($this->twoFactor->isEnrolled($admin) && $this->twoFactor->hasPassed($admin)) {
            return $next($request);
        }

        abort_if(app(HandleRequests::class)->isLivewireRoute(), 403);

        return $this->twoFactor->isEnrolled($admin)
            ? redirect()->guest(route('filament.admin.two-factor.challenge'))
            : redirect()->guest(route('filament.admin.two-factor.setup'));
    }
}
