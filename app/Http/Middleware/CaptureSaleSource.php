<?php

namespace App\Http\Middleware;

use App\Services\Marketing\SaleSourceTracker;
use App\Services\VisitTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Note la provenance du visiteur (Visibilité V4), avant de servir la page pour
 * qu'elle soit enregistrée avec la session. Un souci ici ne doit jamais casser
 * la boutique.
 */
class CaptureSaleSource
{
    public function __construct(
        private readonly SaleSourceTracker $sources,
        private readonly VisitTracker $visits,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Mêmes exclusions que le compteur : ni robots, ni admin, ni appels internes.
            if ($this->visits->shouldCount($request)) {
                $this->sources->capture($request);
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $next($request);
    }
}
