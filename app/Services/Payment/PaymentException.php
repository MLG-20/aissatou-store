<?php

namespace App\Services\Payment;

use RuntimeException;

/**
 * Échec technique côté fournisseur (réseau, refus d'initier). Le message est
 * destiné au client : jamais de détail technique ni de réponse brute.
 */
class PaymentException extends RuntimeException
{
    public static function unreachable(): self
    {
        return new self('Le service de paiement est momentanément indisponible. Vous pouvez réessayer dans un instant.');
    }

    public static function rejected(): self
    {
        return new self('Le paiement n\'a pas pu être initié. Vous pouvez réessayer ou choisir un autre moyen de paiement.');
    }
}
