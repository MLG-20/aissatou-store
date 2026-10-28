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
    case PaymentConfirmed = 'paiement_confirme';
    case OrderShipped = 'commande_expediee';
    case OrderDelivered = 'commande_livree';
    case OrderCancelled = 'commande_annulee';

    /**
     * Modèle à envoyer quand la commande passe à ce statut. Rien pour « En
     * préparation » : trop rapproché de la confirmation.
     */
    public static function forStatus(OrderStatus $status): ?self
    {
        return match ($status) {
            OrderStatus::Shipped => self::OrderShipped,
            OrderStatus::Delivered => self::OrderDelivered,
            OrderStatus::Cancelled => self::OrderCancelled,
            default => null,
        };
    }

    /** @return list<string> */
    public function bodyParameters(Order $order): array
    {
        return match ($this) {
            // Bonjour {{1}}, merci pour votre commande {{2}} d'un montant de {{3}}…
            self::OrderConfirmed,
            // Bonjour {{1}}, nous avons bien reçu le paiement de votre commande {{2}} ({{3}})…
            self::PaymentConfirmed => [self::firstName($order), $order->number, self::amount($order->total)],
            // Bonjour {{1}}, votre commande {{2}} est en route / livrée / annulée…
            self::OrderShipped,
            self::OrderDelivered,
            self::OrderCancelled => [self::firstName($order), $order->number],
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
