<?php

namespace App\Support;

/**
 * Tableau de détails rendu lisible sur une ligne (`clé : valeur · …`), pour
 * les journaux du back-office (paiements, activité admin).
 */
class KeyValueSummary
{
    public static function of(?array $details): ?string
    {
        if (! $details) {
            return null;
        }

        return collect($details)
            ->map(fn ($value, $key) => $key.' : '.match (true) {
                $value === null => '—',
                is_bool($value) => $value ? 'oui' : 'non',
                is_scalar($value) => (string) $value,
                default => json_encode($value, JSON_UNESCAPED_UNICODE),
            })
            ->implode(' · ');
    }
}
