<?php

namespace App\Enums\Order;

use App\Models\Order\Order;
use Illuminate\Support\Str;

/**
 * Modèles de messages WhatsApp, créés et validés chez Meta (catégorie
 * Utilité, langue `fr`) sous ces noms exacts. Les variables {{1}}, {{2}}…
 * du texte sont remplies dans l'ordre de `bodyParameters()` ; le bouton
 * « Suivre ma commande » reçoit l'id de la commande en fin d'URL.
 */
enum WhatsAppTemplate: string
{
    case OrderConfirmed = 'commande_confirmee';

    /** @return list<string> */
    public function bodyParameters(Order $order): array
    {
        return match ($this) {
            // Bonjour {{1}}, merci pour votre commande {{2}} d'un montant de {{3}}…
            self::OrderConfirmed => [self::firstName($order), $order->number, self::amount($order->total)],
        };
    }

    /** Suffixe de l'URL du bouton (null : modèle sans bouton). */
    public function buttonParameter(Order $order): ?string
    {
        return (string) $order->id;
    }

    private static function firstName(Order $order): string
    {
        return Str::before(trim((string) $order->recipient_name), ' ') ?: 'chère cliente';
    }

    private static function amount(mixed $value): string
    {
        return number_format((float) $value, 0, ',', ' ').' FCFA';
    }
}
