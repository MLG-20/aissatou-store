<?php

namespace App\Http\Middleware;

use App\Support\SearchIndexing;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interdit l'indexation par un en-tête plutôt que dans chaque gabarit : couvre
 * aussi le back-office et les pages sans mise en page de la boutique.
 */
class BlockSearchIndexing
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! SearchIndexing::isIndexable($request) && ! $response->headers->has('X-Robots-Tag')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
