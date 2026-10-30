<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Lien de partage Google Maps ou Apple Plans, gardé tel quel : il est ouvert
 * par le livreur et l'admin, donc jamais d'autre site (ni javascript:).
 */
class MapsLink implements ValidationRule
{
    /** Hôtes acceptés, avec le début de chemin exigé ('' = tout chemin). */
    private const HOSTS = [
        'maps.app.goo.gl' => '',
        'goo.gl' => '/maps',
        'maps.google.com' => '',
        'www.google.com' => '/maps',
        'google.com' => '/maps',
        'maps.apple.com' => '',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = is_string($value) ? parse_url(trim($value)) : false;
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        $valid = ($parts['scheme'] ?? '') === 'https'
            && array_key_exists($host, self::HOSTS)
            && str_starts_with($path, self::HOSTS[$host]);

        if (! $valid) {
            $fail('Collez un lien de partage Google Maps (https://maps.app.goo.gl/…) ou Apple Plans.');
        }
    }
}
