<?php

namespace App\Exceptions;

/**
 * Code promo refusé (Phase 6C). Hérite d'OrderException : un refus à la
 * validation finale annule la commande entière (rien n'est créé) et le
 * tunnel l'affiche comme toute autre erreur de commande.
 */
class PromoCodeException extends OrderException
{
    /** Code inconnu OU désactivé : même message, on ne confirme pas qu'un code existe. */
    public static function invalid(): self
    {
        return new self('Ce code promo n\'est pas valide.');
    }

    public static function notStarted(): self
    {
        return new self('Ce code promo n\'est pas encore actif.');
    }

    public static function expired(): self
    {
        return new self('Ce code promo a expiré.');
    }

    public static function exhausted(): self
    {
        return new self('Ce code promo a atteint son nombre maximum d\'utilisations.');
    }

    public static function alreadyUsed(): self
    {
        return new self('Vous avez déjà utilisé ce code promo.');
    }

    public static function minimumNotReached(float $minimum): self
    {
        return new self('Ce code promo exige un minimum d\'achat de '.number_format($minimum, 0, ',', ' ').' FCFA (hors livraison).');
    }

    public static function notApplicable(): self
    {
        return new self('Ce code promo ne s\'applique à aucun article de votre panier.');
    }
}
