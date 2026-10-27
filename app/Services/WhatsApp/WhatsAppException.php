<?php

namespace App\Services\WhatsApp;

use RuntimeException;

/**
 * Échec d'envoi chez Meta. Le message est destiné au back-office (journal
 * de la commande) : code et texte de Meta, jamais le jeton.
 * `retryable` : panne passagère (réseau, 5xx, limite de débit) qui mérite
 * une nouvelle tentative ; sinon (numéro sans WhatsApp, modèle inconnu…)
 * réessayer ne changerait rien.
 */
class WhatsAppException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }

    public static function unreachable(): self
    {
        return new self('Meta injoignable', retryable: true);
    }

    /** @param  array<string, mixed>|null  $error  champ `error` de la réponse Meta */
    public static function rejected(int $httpStatus, ?array $error): self
    {
        $detail = trim(($error['code'] ?? $httpStatus).' : '.($error['error_data']['details'] ?? $error['message'] ?? 'refus'));

        return new self(mb_substr('Refus de Meta '.$detail, 0, 255), retryable: $httpStatus >= 500 || $httpStatus === 429);
    }
}
