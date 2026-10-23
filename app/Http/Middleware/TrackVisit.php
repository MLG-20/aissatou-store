<?php

namespace App\Http\Middleware;

use App\Services\VisitTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Compte la visite APRÈS avoir servi la page (Phase 6D) : un souci de
 * comptage ne doit jamais casser ni ralentir la boutique.
 */
class TrackVisit
{
    public function __construct(private readonly VisitTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $response->isSuccessful() || ! $this->tracker->shouldCount($request)) {
            return;
        }

        try {
            $this->tracker->record($request);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
