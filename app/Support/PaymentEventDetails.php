<?php

namespace App\Support;

use App\Enums\Order\PaymentStatus;

/**
 * Traduit les détails bruts d'une ligne du journal des paiements (clés
 * PayTech : `type_event`, `item_price`…) en libellés compréhensibles par la
 * gérante, pour la fiche de l'événement et la fiche commande.
 */
class PaymentEventDetails
{
    private const LABELS = [
        'type_event' => 'Message de PayTech',
        'motif' => 'Motif du rejet',
        'amount' => 'Montant demandé',
        'item_price' => 'Montant demandé',
        'final_item_price' => 'Montant débité',
        'payment_method' => 'Moyen de paiement',
        'client_phone' => 'Téléphone du payeur',
        'remote' => 'Réponse de PayTech',
        'source' => 'Origine',
        'error' => 'Erreur',
        'message' => 'Message d\'erreur de PayTech',
        'http' => 'Code de réponse',
        'auth' => 'Authenticité du message',
        'env' => 'Mode',
        'ref_command' => 'Référence de la tentative',
        'token' => 'Identifiant PayTech',
    ];

    /** @return array<string, string> libellé => valeur, dans l'ordre de lecture */
    public static function of(?array $payload): array
    {
        if (! $payload) {
            return [];
        }

        // Ordre fixe (le plus parlant d'abord), clés inconnues à la fin.
        $ordered = array_merge(
            array_intersect_key(array_flip(array_keys(self::LABELS)), $payload),
            $payload,
        );

        $details = [];

        foreach ($ordered as $key => $value) {
            $details[self::LABELS[$key] ?? $key] = self::value($key, $value);
        }

        return $details;
    }

    /** Résumé sur une ligne, pour les listes. */
    public static function summary(?array $payload): ?string
    {
        $details = self::of($payload);

        if (! $details) {
            return null;
        }

        return collect($details)->map(fn ($value, $label) => $label.' : '.$value)->implode(' · ');
    }

    private static function value(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($key) {
            'amount', 'item_price', 'final_item_price' => number_format((float) $value, 0, ',', ' ').' FCFA',
            'type_event' => match ($value) {
                'sale_complete' => 'Paiement réussi',
                'sale_canceled' => 'Paiement annulé',
                default => (string) $value,
            },
            'client_phone' => 'se terminant par '.ltrim((string) $value, '…'),
            'remote' => PaymentStatus::tryFrom((string) $value)?->label() ?? 'Pas de réponse',
            'source' => match ($value) {
                'verification' => 'Vérification auprès de PayTech',
                'expiration' => 'Délai de paiement dépassé',
                default => (string) $value,
            },
            'error' => $value === 'injoignable' ? 'PayTech injoignable' : (string) $value,
            'auth' => match ($value) {
                'hmac' => 'Signature vérifiée',
                'sha256' => 'Non signé, confirmé par vérification auprès de PayTech',
                default => (string) $value,
            },
            'env' => match ($value) {
                'test' => 'Test (aucun argent réel)',
                'prod' => 'Réel',
                default => (string) $value,
            },
            default => match (true) {
                is_bool($value) => $value ? 'oui' : 'non',
                is_scalar($value) => (string) $value,
                default => json_encode($value, JSON_UNESCAPED_UNICODE),
            },
        };
    }
}
