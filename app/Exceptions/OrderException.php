<?php

namespace App\Exceptions;

use RuntimeException;

class OrderException extends RuntimeException
{
    public static function emptyCart(): self
    {
        return new self('Votre panier est vide.');
    }

    public static function productUnavailable(): self
    {
        return new self('Un article de votre panier n\'est plus disponible.');
    }

    public static function insufficientStock(): self
    {
        return new self('Le stock d\'un article est insuffisant pour votre quantité.');
    }

    public static function paymentUnavailable(): self
    {
        return new self('Ce mode de paiement n\'est pas disponible.');
    }

    public static function invalidTransition(): self
    {
        return new self('Ce changement de statut n\'est pas autorisé.');
    }
}
