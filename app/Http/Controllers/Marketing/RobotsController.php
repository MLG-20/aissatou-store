<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Consignes aux robots. Le panier et le compte n'y figurent pas : une page
 * bloquée ici n'est jamais lue, son « noindex » serait ignoré.
 */
class RobotsController extends Controller
{
    private const BLOCKED_PATHS = ['/admin', '/api/', '/webhooks/', '/paiement/', '/livraison/', '/tournee/'];

    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            ...array_map(fn (string $path): string => 'Disallow: '.$path, self::BLOCKED_PATHS),
            '',
            'Sitemap: '.route('marketing.sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
