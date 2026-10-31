<?php

namespace App\Services\Marketing;

use App\Models\Content\SaleSource;
use Illuminate\Http\Request;

/**
 * Retient d'où vient le visiteur, le temps de sa session (Visibilité V4,
 * décision Ahmed) : aucun cookie supplémentaire, donc pas de bandeau.
 *
 * Un lien de suivi (`?src=`, ou `utm_source` pour les publicités) l'emporte
 * toujours. À défaut, le site d'origine annoncé par le navigateur sert à
 * deviner la source, sans jamais remplacer celle déjà retenue.
 */
class SaleSourceTracker
{
    public const SESSION_KEY = 'sale_source_id';

    /** Code de la source => site d'origine reconnu. */
    private const REFERRERS = [
        'instagram' => '/(^|\.)instagram\.com$/',
        'tiktok' => '/(^|\.)tiktok\.com$/',
        'facebook' => '/(^|\.)(facebook\.com|fb\.com)$/',
        'google' => '/(^|\.)google\.[a-z.]+$/',
    ];

    public function capture(Request $request): void
    {
        $code = $request->query('src') ?? $request->query('utm_source');

        if (is_string($code) && $code !== '') {
            $source = $this->find(strtolower($code));

            if ($source) {
                $request->session()->put(self::SESSION_KEY, $source->id);

                return;
            }
        }

        if ($request->session()->has(self::SESSION_KEY)) {
            return;
        }

        $code = $this->codeFromReferrer($request);
        $source = $code ? $this->find($code) : null;

        if ($source) {
            $request->session()->put(self::SESSION_KEY, $source->id);
        }
    }

    public function currentId(Request $request): ?int
    {
        return $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;
    }

    private function find(string $code): ?SaleSource
    {
        return SaleSource::query()->active()->where('code', $code)->first();
    }

    private function codeFromReferrer(Request $request): ?string
    {
        $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);

        if (! is_string($host)) {
            return null;
        }

        foreach (self::REFERRERS as $code => $pattern) {
            if (preg_match($pattern, strtolower($host))) {
                return $code;
            }
        }

        return null;
    }
}
