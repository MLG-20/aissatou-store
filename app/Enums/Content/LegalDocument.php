<?php

namespace App\Enums\Content;

/**
 * Les pages légales de la boutique : une liste fermée, chacune à une adresse
 * fixe (la valeur du cas). La cliente en écrit le texte dans Filament, mais
 * n'en crée ni n'en supprime : le pied de page et les liens (paiement,
 * WhatsApp…) comptent sur ces adresses.
 */
enum LegalDocument: string
{
    case Notice = 'mentions-legales';
    case Privacy = 'confidentialite';
    case Terms = 'cgv';
    case Cookies = 'cookies';

    public function title(): string
    {
        return match ($this) {
            self::Notice => 'Mentions légales',
            self::Privacy => 'Politique de confidentialité',
            self::Terms => 'Conditions générales de vente',
            self::Cookies => 'Politique cookies',
        };
    }

    /** Libellé court du pied de page. */
    public function shortTitle(): string
    {
        return match ($this) {
            self::Notice => 'Mentions légales',
            self::Privacy => 'Confidentialité',
            self::Terms => 'CGV',
            self::Cookies => 'Cookies',
        };
    }
}
