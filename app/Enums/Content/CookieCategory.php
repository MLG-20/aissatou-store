<?php

namespace App\Enums\Content;

/**
 * Familles de cookies du registre (admin > Cookies et traceurs). Seuls les
 * cookies nécessaires se passent de consentement : pour tous les autres, le
 * bandeau demande l'accord du visiteur avant que leur script ne se charge.
 */
enum CookieCategory: string
{
    case Necessary = 'necessaire';
    case Audience = 'mesure-audience';
    case Monitoring = 'suivi-technique';
    case Marketing = 'marketing';

    public function label(): string
    {
        return match ($this) {
            self::Necessary => 'Nécessaires au fonctionnement',
            self::Audience => 'Mesure d\'audience',
            self::Monitoring => 'Suivi technique (erreurs, performances)',
            self::Marketing => 'Publicité et réseaux sociaux',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Necessary => 'Indispensables au panier, à la connexion et à la sécurité du site. Ils ne peuvent pas être refusés.',
            self::Audience => 'Nous aident à comprendre comment le site est consulté, pour l\'améliorer.',
            self::Monitoring => 'Signalent les erreurs et lenteurs rencontrées, pour les corriger.',
            self::Marketing => 'Mesurent l\'efficacité de nos publicités et permettent le partage sur les réseaux sociaux.',
        };
    }

    public function requiresConsent(): bool
    {
        return $this !== self::Necessary;
    }
}
