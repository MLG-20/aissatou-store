<?php

namespace App\Enums\Admin;

/**
 * Permissions du back-office (Phase 6A), par domaine. Chaque ressource ou
 * page Filament en exige une, revérifiée côté serveur à chaque requête —
 * jamais seulement en masquant un lien de navigation.
 *
 * Étendre la délégation = ajouter un cas ici puis l'attribuer à un rôle dans
 * AdminRoles : aucune ressource existante à modifier.
 */
enum AdminPermission: string
{
    case Catalogue = 'catalogue.manage';
    case Stock = 'stock.manage';
    case Orders = 'orders.manage';
    case Content = 'content.manage';
    case Delivery = 'delivery.manage';
    case Payments = 'payments.view';
    case Promotions = 'promotions.manage';
    case Statistics = 'statistics.view';
    case Customers = 'customers.view';
    case Admins = 'admins.manage';
    case Settings = 'settings.manage';

    public function label(): string
    {
        return match ($this) {
            self::Catalogue => 'Catalogue',
            self::Stock => 'Stock',
            self::Orders => 'Commandes',
            self::Content => 'Contenu du site',
            self::Delivery => 'Zones de livraison',
            self::Payments => 'Journal des paiements',
            self::Promotions => 'Codes promo',
            self::Statistics => 'Statistiques',
            self::Customers => 'Clients',
            self::Admins => 'Comptes admin',
            self::Settings => 'Réglages du site',
        };
    }
}
