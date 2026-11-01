<?php

namespace App\Support;

use App\Enums\Admin\AdminPermission as P;
use App\Models\Admin;

/**
 * Boutons de la page Aide : chacun lance une visite guidée sur le vrai écran.
 * Les étapes sont dans resources/js/admin-guides-data.js, car elles suivent les
 * écrans. `path` = premier écran du guide, sous /admin.
 */
class AdminGuides
{
    /** @return array<string, list<array{key: string, title: string, description: string, icon: string, path: string, permission: ?P}>> */
    public static function all(): array
    {
        $guide = fn (string $key, string $title, string $description, string $icon, string $path, ?P $permission = null): array => compact('key', 'title', 'description', 'icon', 'path', 'permission');

        return [
            'Bien démarrer' => [
                $guide('decouvrir-le-menu', 'Découvrir le menu', 'À quoi sert chaque rubrique.', 'heroicon-o-map', ''),
                $guide('lire-mes-chiffres', 'Lire mes chiffres', 'Comprendre le tableau de bord.', 'heroicon-o-chart-bar', '', P::Statistics),
            ],
            'Mes commandes' => [
                $guide('traiter-une-commande', 'Traiter une commande', 'De la réception à la livraison.', 'heroicon-o-shopping-bag', 'order/orders', P::Orders),
                $guide('envoyer-au-livreur', 'Envoyer au livreur', 'Choisir le livreur et le prévenir.', 'heroicon-o-paper-airplane', 'order/orders', P::Orders),
                $guide('verifier-un-paiement', 'Vérifier un paiement', 'Quand une cliente dit avoir payé.', 'heroicon-o-credit-card', 'paiements/suivi', P::Payments),
                $guide('facture', 'Obtenir une facture', 'Télécharger le PDF d\'une commande.', 'heroicon-o-document-text', 'order/orders', P::Orders),
            ],
            'Mes produits' => [
                $guide('ajouter-un-produit', 'Ajouter un produit', 'Avec ses photos et sa quantité.', 'heroicon-o-plus-circle', 'catalogue/products', P::Catalogue),
                $guide('modifier-un-produit', 'Modifier ou arrêter un produit', 'Prix, photos, retrait de la boutique.', 'heroicon-o-pencil-square', 'catalogue/products', P::Catalogue),
                $guide('ajouter-une-categorie', 'Ajouter un rayon', 'Une catégorie dans le menu du site.', 'heroicon-o-folder-plus', 'catalogue/categories', P::Catalogue),
                $guide('creer-une-collection', 'Créer une collection', 'Regrouper des produits par thème.', 'heroicon-o-squares-2x2', 'catalogue/collections', P::Catalogue),
                $guide('gerer-les-matieres', 'Gérer les matières', 'La liste du filtre « Matière ».', 'heroicon-o-swatch', 'catalogue/materials', P::Catalogue),
            ],
            'Mon stock' => [
                $guide('changer-une-quantite', 'Changer une quantité', 'Quand vous recevez de la marchandise.', 'heroicon-o-archive-box-arrow-down', 'stock/inventaire', P::Stock),
                $guide('surveiller-le-stock', 'Surveiller mon stock', 'Voir ce qu\'il reste et ce qui a bougé.', 'heroicon-o-archive-box', 'stock/inventaire', P::Stock),
            ],
            'La livraison' => [
                $guide('ajouter-une-zone', 'Fixer un prix de livraison', 'Ajouter une zone et son tarif.', 'heroicon-o-map-pin', 'delivery/delivery-zones', P::Delivery),
                $guide('ajouter-un-livreur', 'Ajouter un livreur', 'Son nom et son WhatsApp.', 'heroicon-o-truck', 'delivery/couriers', P::Delivery),
                $guide('ma-tournee', 'Envoyer « Ma tournée »', 'Toutes ses livraisons sur son téléphone.', 'heroicon-o-device-phone-mobile', 'delivery/couriers', P::Delivery),
            ],
            'Faire connaître la boutique' => [
                $guide('creer-un-code-promo', 'Créer un code promo', 'Offrir une réduction.', 'heroicon-o-ticket', 'codes-promo', P::Promotions),
                $guide('liens-de-suivi', 'Utiliser les liens de suivi', 'Savoir d\'où viennent vos ventes.', 'heroicon-o-link', 'content/sale-sources', P::Content),
            ],
            'Le contenu du site' => [
                $guide('changer-une-photo-accueil', 'Changer une image de l\'accueil', 'Les grandes images en haut du site.', 'heroicon-o-photo', 'content/hero-slides', P::Content),
                $guide('ajouter-un-look', 'Ajouter un look', 'Une tenue portée, avec ses produits.', 'heroicon-o-sparkles', 'content/looks', P::Content),
                $guide('ecrire-un-article', 'Écrire un article', 'Publier dans le Journal.', 'heroicon-o-newspaper', 'content/posts', P::Content),
                $guide('gerer-les-avis', 'Publier un avis', 'Choisir les avis visibles.', 'heroicon-o-chat-bubble-left-right', 'content/reviews', P::Content),
                $guide('mes-coordonnees', 'Changer mes coordonnées', 'WhatsApp, réseaux et réglages.', 'heroicon-o-cog-6-tooth', 'site-settings', P::Settings),
                $guide('pages-legales', 'Modifier les pages légales', 'Conditions de vente, confidentialité…', 'heroicon-o-scale', 'content/legal-pages', P::Settings),
            ],
            'Mes clientes et messages' => [
                $guide('trouver-une-cliente', 'Trouver une cliente', 'Ses achats, son panier, ses adresses.', 'heroicon-o-users', 'clients', P::Customers),
                $guide('repondre-a-un-message', 'Répondre à un message', 'Le formulaire de contact du site.', 'heroicon-o-envelope', 'content/contact-messages', P::Content),
            ],
            'Mon équipe' => [
                $guide('ajouter-un-membre', 'Ajouter un membre', 'Créer un compte et choisir son rôle.', 'heroicon-o-user-plus', 'admin/admins', P::Admins),
                $guide('debloquer-un-membre', 'Débloquer un membre', 'Téléphone perdu : réinitialiser sa double authentification.', 'heroicon-o-device-phone-mobile', 'admin/admins', P::Admins),
                $guide('journal-activite', 'Voir qui a fait quoi', 'Le journal d\'activité.', 'heroicon-o-clipboard-document-list', 'admin/admin-activities', P::Admins),
            ],
        ];
    }

    /** @return array<string, list<array{title: string, description: string, icon: string, url: string}>> par rubrique, sans celles qui restent vides */
    public static function for(Admin $admin): array
    {
        return collect(self::all())
            ->map(fn (array $guides): array => collect($guides)
                ->filter(fn (array $guide): bool => $guide['permission'] === null || $admin->hasAdminPermission($guide['permission']))
                ->map(fn (array $guide): array => [
                    'title' => $guide['title'],
                    'description' => $guide['description'],
                    'icon' => $guide['icon'],
                    'url' => url('admin/'.$guide['path']).'?guide='.$guide['key'],
                ])
                ->values()
                ->all())
            ->filter()
            ->all();
    }
}
