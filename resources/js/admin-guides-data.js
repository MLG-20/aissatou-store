/*
 * Étapes des visites guidées. Les éléments sont retrouvés par ce que la personne
 * voit à l'écran (libellé, titre, bouton) : si un écran change, son guide est à
 * revoir. Liste des boutons de la page Aide : App\Support\AdminGuides.
 *
 * clickEnds : le bouton ouvre une fenêtre à remplir, le guide lui laisse la main.
 */
const byText = (selector, text) => () => [...document.querySelectorAll(selector)]
    .find((element) => element.textContent.trim().replace(/\s+/g, ' ').startsWith(text));

const field = (label) => () => [...document.querySelectorAll('.fi-fo-field-wrp')]
    .find((wrapper) => wrapper.querySelector('label, .fi-fo-field-wrp-label')?.textContent.trim().startsWith(label));

const button = (text) => byText('a.fi-btn, button.fi-btn, a.fi-link, button.fi-link', text);
const section = (title) => () => byText('.fi-section-header-heading', title)()?.closest('.fi-section');
const nav = (label) => byText('.fi-sidebar-item', label);
const stat = (label) => byText('.fi-wi-stats-overview-stat', label);
const table = () => document.querySelector('.fi-ta');
const search = () => document.querySelector('.fi-ta-search-field');
const submit = () => document.querySelector('.fi-form-actions button[type="submit"], .fi-page form button[type="submit"]');
const row = (fragment) => () => document.querySelector(`.fi-ta-row a[href*="${fragment}"]`)?.closest('.fi-ta-row');
// Toutes les lignes : la première peut être un titre de groupe, sans bouton.
const rowButton = (text) => byText('.fi-ta-row a, .fi-ta-row button', text);

const SAVE = 'Quand tout est rempli, cliquez sur ce bouton pour enregistrer. S\'il manque quelque chose, un message rouge vous indiquera quoi.';

export const GUIDES = {
    'decouvrir-le-menu': [
        { path: '', element: nav('Tableau de bord'), title: 'Vos chiffres', text: 'Ici, vous voyez d\'un coup d\'œil vos ventes et vos visites.' },
        { path: '', element: nav('Commandes'), title: 'Vos commandes', text: 'C\'est ici que vous suivez chaque commande, de la réception à la livraison. C\'est l\'écran à ouvrir chaque jour.' },
        { path: '', element: nav('Produits'), title: 'Vos produits', text: 'Pour ajouter un produit ou modifier sa photo, son prix ou sa description.' },
        { path: '', element: nav('Suivi du stock'), title: 'Votre stock', text: 'Pour voir ce qu\'il vous reste et changer une quantité quand vous recevez de la marchandise.' },
        { path: '', element: nav('Livreurs'), title: 'Vos livreurs', text: 'La liste des personnes qui livrent vos commandes, et les tarifs par zone juste en dessous.' },
        { path: '', element: nav('Réglages du site'), title: 'Les informations de la boutique', text: 'Votre WhatsApp, votre adresse, vos réseaux sociaux et les textes généraux du site.' },
        { path: '', element: nav('Aide'), title: 'Besoin d\'aide ?', text: 'Revenez ici quand vous voulez : chaque guide peut être relancé autant de fois que nécessaire.' },
    ],
    'lire-mes-chiffres': [
        { path: '', element: field('Période'), title: 'Choisir la période', text: 'Choisissez d\'abord ce que vous voulez regarder : aujourd\'hui, les 7 derniers jours ou les 30 derniers jours.' },
        { path: '', element: stat('Chiffre d\'affaires'), title: 'Le chiffre d\'affaires', text: 'L\'argent réellement encaissé sur la période. Une commande non payée n\'y est pas comptée.' },
        { path: '', element: stat('Commandes payées'), title: 'Les commandes payées', text: 'Le nombre de commandes dont le paiement est confirmé.' },
        { path: '', element: stat('Panier moyen'), title: 'Le panier moyen', text: 'Ce qu\'une cliente dépense en moyenne par commande.' },
        { path: '', element: stat('Taux de conversion'), title: 'Le taux de conversion', text: 'Sur 100 visiteurs, combien ont passé commande. 2 % veut dire 2 commandes pour 100 visiteurs.' },
        { path: '', element: stat('Paniers abandonnés'), title: 'Les paniers abandonnés', text: 'Les clientes qui ont mis des articles dans leur panier sans commander depuis plus de 24 heures.' },
        { path: '', element: section('Ventes par source'), title: 'D\'où viennent vos ventes', text: 'Pour savoir si vos clientes arrivent de TikTok, d\'Instagram ou de vos statuts WhatsApp. « Non renseignée » veut dire qu\'on ne sait pas d\'où elles viennent.' },
    ],
    'traiter-une-commande': [
        { path: 'order/orders', element: table, title: 'Vos commandes', text: 'Les commandes sont ici. Regardez surtout les colonnes « Statut » et « Paiement ».' },
        { path: 'order/orders', element: row('/order/orders/'), title: 'Ouvrir une commande', text: 'Cliquez sur une commande pour voir tout son détail.', emptyText: 'Vous n\'avez pas encore de commande. Revenez à ce guide quand la première sera arrivée.' },
        { path: 'order/orders/*', element: section('Client et livraison'), title: 'Qui et où', text: 'Le nom de la cliente, son téléphone et son adresse de livraison.' },
        { path: 'order/orders/*', element: section('Articles'), title: 'Ce qu\'elle a commandé', text: 'Préparez exactement ces articles. Les prix sont ceux du jour de l\'achat.' },
        { path: 'order/orders/*', element: section('Paiement'), title: 'Le paiement', text: 'Vérifiez ici que la commande est bien payée avant de la préparer.' },
        { path: 'order/orders/*', element: button('Changer le statut'), title: 'Faire avancer la commande', text: 'Cliquez ici à chaque étape : En préparation, puis Expédiée, puis Livrée. Une annulation reste possible tant que la commande n\'est pas expédiée, et le stock est alors remis automatiquement.', clickEnds: true },
    ],
    'envoyer-au-livreur': [
        { path: 'order/orders', element: row('/order/orders/'), title: 'Ouvrir la commande', text: 'Cliquez sur la commande à faire livrer.', emptyText: 'Vous n\'avez pas encore de commande. Revenez à ce guide quand la première sera arrivée.' },
        { path: 'order/orders/*', element: section('Livraison'), title: 'La livraison', text: 'Vous voyez ici la zone de livraison et le livreur choisi.' },
        { path: 'order/orders/*', element: button('Organiser la livraison'), title: 'Choisir le livreur', text: 'Cliquez ici et choisissez le livreur dans la liste. Sans livreur de la liste, écrivez l\'autre mode, par exemple « retrait boutique ». Après l\'enregistrement, cliquez sur « Envoyer au livreur ».', clickEnds: true },
        { path: 'order/orders/*', element: button('Envoyer au livreur'), title: 'Prévenir le livreur', text: 'Ce bouton ouvre WhatsApp avec le lien de sa page de livraison. Il n\'apparaît que lorsqu\'un livreur est choisi.' },
    ],
    'verifier-un-paiement': [
        { path: 'paiements/suivi', element: table, title: 'Tous les paiements', text: 'Chaque paiement est ici, avec son montant, sa cliente et son statut.' },
        { path: 'paiements/suivi', element: row('/paiements/suivi/'), title: 'Ouvrir un paiement', text: 'Cliquez sur un paiement pour voir ce qui s\'est passé.', emptyText: 'Aucun paiement pour le moment. Revenez à ce guide après une première commande.' },
        { path: 'paiements/suivi/*', element: section('Résumé'), title: 'L\'essentiel', text: 'Le montant, le moyen de paiement et le statut. S\'il reste impayé après le délai, la commande s\'annule toute seule et le stock est remis.' },
        { path: 'paiements/suivi/*', element: section('Déroulement'), title: 'L\'histoire du paiement', text: 'Chaque étape est racontée ici. Une cliente dit avoir payé mais la commande est en attente ? Ouvrez la commande et cliquez sur « Vérifier le paiement ».' },
    ],
    'facture': [
        { path: 'order/orders', element: row('/order/orders/'), title: 'Ouvrir la commande', text: 'Cliquez sur la commande concernée.', emptyText: 'Vous n\'avez pas encore de commande. Revenez à ce guide quand la première sera arrivée.' },
        { path: 'order/orders/*', element: button('Facture PDF'), title: 'Télécharger la facture', text: 'Cliquez ici pour obtenir la facture, à imprimer ou à envoyer à la cliente.' },
    ],
    'ajouter-un-produit': [
        { path: 'catalogue/products', element: button('Créer'), title: 'Commençons', text: 'Cliquez sur ce bouton pour créer un nouveau produit.' },
        { path: 'catalogue/products/create', element: field('Nom'), title: 'Le nom du produit', text: 'Écrivez son nom, par exemple « Hijab Jersey ». S\'il existe en plusieurs couleurs, ne mettez pas la couleur dans le nom.' },
        { path: 'catalogue/products/create', element: field('Catégorie'), title: 'Le rayon', text: 'Choisissez où le produit sera rangé dans la boutique.' },
        { path: 'catalogue/products/create', element: field('Prix (FCFA)'), title: 'Le prix', text: 'Écrivez le prix en chiffres, sans espace ni « FCFA ». Par exemple : 8000.' },
        { path: 'catalogue/products/create', element: field('Images'), title: 'Les photos', text: 'Cliquez sur « Ajouter à images » pour déposer une photo. Sur la plus belle, cochez « Photo principale » : c\'est elle qu\'on verra dans la boutique.' },
        { path: 'catalogue/products/create', element: field('Déclinaisons'), title: 'Les couleurs et les tailles', text: 'Faites une ligne par couleur ou par taille. Si c\'est un modèle unique, gardez une seule ligne et laissez « Caractéristiques » vide.' },
        { path: 'catalogue/products/create', element: field('Stock initial'), title: 'La quantité', text: 'Écrivez combien vous en avez. Pour une pièce unique, écrivez 1. Le site enlèvera lui-même chaque article vendu.' },
        { path: 'catalogue/products/create', element: field('Visible sur le site'), title: 'Montrer le produit', text: 'Activé, les clientes verront le produit dès l\'enregistrement. Désactivé, il reste caché : pratique pour le terminer plus tard.' },
        { path: 'catalogue/products/create', element: submit, title: 'Dernière étape', text: SAVE },
    ],
    'modifier-un-produit': [
        { path: 'catalogue/products', element: search, title: 'Trouver le produit', text: 'Écrivez ici une partie de son nom.' },
        { path: 'catalogue/products', element: rowButton('Modifier'), title: 'Ouvrir sa fiche', text: 'Cliquez sur « Modifier » à côté du produit.', emptyText: 'Vous n\'avez pas encore de produit. Commencez par le guide « Ajouter un produit ».' },
        { path: 'catalogue/products/*/edit', element: field('Prix (FCFA)'), title: 'Le prix', text: 'Changez le prix ici si besoin. Les commandes déjà passées gardent leur ancien prix.' },
        { path: 'catalogue/products/*/edit', element: field('Images'), title: 'Les photos', text: 'Laissez une photo telle quelle pour la garder, ou déposez un fichier pour la remplacer.' },
        { path: 'catalogue/products/*/edit', element: field('Visible sur le site'), title: 'Arrêter un produit', text: 'Désactivez ceci pour retirer le produit de la boutique sans perdre sa fiche.' },
        { path: 'catalogue/products/*/edit', element: submit, title: 'Enregistrer', text: SAVE },
    ],
    'ajouter-une-categorie': [
        { path: 'catalogue/categories', element: button('Créer'), title: 'Nouveau rayon', text: 'Les catégories sont les rayons de votre boutique : Hijabs, Foulards… Cliquez ici pour en créer une.' },
        { path: 'catalogue/categories/create', element: field('Nom'), title: 'Le nom', text: 'C\'est le nom que verront les clientes dans le menu.' },
        { path: 'catalogue/categories/create', element: field('Catégorie parente'), title: 'Rayon ou sous-rayon', text: 'Laissez vide pour un rayon principal. Choisissez un rayon pour en faire un sous-rayon.' },
        { path: 'catalogue/categories/create', element: field('Visible sur le site'), title: 'Montrer le rayon', text: 'Désactivé, le rayon reste caché aux clientes.' },
        { path: 'catalogue/categories/create', element: submit, title: 'Enregistrer', text: SAVE },
    ],
    'creer-une-collection': [
        { path: 'catalogue/collections', element: button('Créer'), title: 'Nouvelle collection', text: 'Une collection regroupe des produits autour d\'un thème, comme « Tabaski ». Cliquez ici pour la créer.' },
        { path: 'catalogue/collections/create', element: field('Nom'), title: 'Le nom', text: 'Choisissez un nom qui donne envie.' },
        { path: 'catalogue/collections/create', element: field('Image'), title: 'L\'image', text: 'Déposez une belle photo pour représenter la collection.' },
        { path: 'catalogue/collections/create', element: field('Produits de la collection'), title: 'Les produits', text: 'Choisissez les produits à y mettre. Un produit peut être dans plusieurs collections.' },
        { path: 'catalogue/collections/create', element: submit, title: 'Enregistrer', text: SAVE },
    ],
    'gerer-les-matieres': [
        { path: 'catalogue/materials', element: table, title: 'Les matières', text: 'C\'est la liste proposée aux clientes dans le filtre « Matière » de la boutique.' },
        { path: 'catalogue/materials', element: button('Créer'), title: 'Ajouter une matière', text: 'Cliquez ici, écrivez son nom et enregistrez. Vous pourrez ensuite la choisir sur vos fiches produit.' },
    ],
    'changer-une-quantite': [
        { path: 'stock/inventaire', element: search, title: 'Trouver l\'article', text: 'Écrivez ici une partie du nom du produit.' },
        { path: 'stock/inventaire', element: rowButton('Ajuster le stock'), title: 'Changer la quantité', text: 'Cliquez ici. Dans la fenêtre, écrivez le NOUVEAU TOTAL et le motif. S\'il vous en reste 3 et que vous en recevez 10, écrivez 13.', clickEnds: true },
    ],
    'surveiller-le-stock': [
        { path: 'stock/inventaire', element: table, title: 'Tout votre stock', text: 'Une ligne par article, avec sa quantité et son état. Une alerte vous est aussi envoyée par e-mail quand un article passe sous son seuil.' },
        { path: 'stock/inventaire', element: rowButton('Voir la fiche'), title: 'L\'historique d\'un article', text: 'Cliquez ici pour voir chaque entrée et sortie : ventes, annulations et ajustements.' },
        { path: 'stock/mouvements', element: table, title: 'Tous les mouvements', text: 'Cet écran raconte tout ce qui a bougé dans le stock, par qui et pourquoi. Pratique quand un chiffre vous étonne.' },
    ],
    'ajouter-une-zone': [
        { path: 'delivery/delivery-zones', element: button('Créer'), title: 'Nouvelle zone', text: 'Une zone fixe le prix de livraison pour une ville. Cliquez ici pour en ajouter une.' },
        { path: 'delivery/delivery-zones/create', element: field('Nom de la zone'), title: 'Le nom', text: 'Par exemple « Dakar Plateau ».' },
        { path: 'delivery/delivery-zones/create', element: field('Ville couverte'), title: 'La ville', text: 'Ce prix s\'applique quand l\'adresse de la cliente est dans cette ville. Les majuscules et les accents n\'ont pas d\'importance.' },
        { path: 'delivery/delivery-zones/create', element: field('Frais (FCFA)'), title: 'Le prix', text: 'Le prix de la livraison, en chiffres.' },
        { path: 'delivery/delivery-zones/create', element: field('Délai estimé'), title: 'Le délai', text: 'Par exemple « 24 h ». La cliente le voit au moment de commander.' },
        { path: 'delivery/delivery-zones/create', element: submit, title: 'Enregistrer', text: SAVE },
    ],
    'ajouter-un-livreur': [
        { path: 'delivery/couriers', element: button('Créer'), title: 'Nouveau livreur', text: 'Cliquez ici pour ajouter une personne qui livre pour vous.' },
        { path: 'delivery/couriers/create', element: field('Nom'), title: 'Son nom', text: 'Le nom que vous verrez en choisissant le livreur d\'une commande.' },
        { path: 'delivery/couriers/create', element: field('Numéro WhatsApp'), title: 'Son WhatsApp', text: 'C\'est là que vous lui enverrez ses livraisons.' },
        { path: 'delivery/couriers/create', element: submit, title: 'Enregistrer', text: SAVE },
    ],
    'ma-tournee': [
        { path: 'delivery/couriers', element: table, title: 'Vos livreurs', text: 'La colonne « En cours » montre combien de commandes chacun doit livrer.' },
        { path: 'delivery/couriers', element: () => rowButton('Envoyer')() ?? rowButton('Renvoyer')(), title: 'Le lien « Ma tournée »', text: 'Cliquez ici pour lui envoyer son lien : il verra toutes ses livraisons sur son téléphone, sans compte. Un nouvel envoi désactive l\'ancien lien. Le bouton « Révoquer » le coupe, par exemple si ce livreur ne travaille plus avec vous.', clickEnds: true, emptyText: 'Ajoutez d\'abord un livreur avec le guide « Ajouter un livreur ».' },
    ],
    'creer-un-code-promo': [
        { path: 'codes-promo', element: button('Créer'), title: 'Nouveau code promo', text: 'Cliquez ici pour créer une réduction.' },
        { path: 'codes-promo/create', element: field('Code'), title: 'Le code', text: 'Le mot que la cliente tapera, par exemple TABASKI10. Choisissez-le simple.' },
        { path: 'codes-promo/create', element: section('Réduction'), title: 'La réduction', text: 'Choisissez un pourcentage ou un montant, puis sa valeur. La livraison n\'est jamais réduite.' },
        { path: 'codes-promo/create', element: section('Conditions'), title: 'Les limites', text: 'Facultatif : dates de validité, montant minimum, nombre d\'utilisations.' },
        { path: 'codes-promo/create', element: submit, title: 'Enregistrer', text: SAVE },
    ],
    'liens-de-suivi': [
        { path: 'content/sale-sources', element: table, title: 'Un lien par réseau', text: 'Copiez le lien du réseau en cliquant dessus, puis collez-le dans votre bio ou votre statut. Vous saurez ensuite d\'où viennent vos ventes dans le tableau de bord.' },
        { path: 'content/sale-sources', element: button('Créer'), title: 'Ajouter une source', text: 'Pour suivre une influenceuse ou une campagne, cliquez ici et donnez-lui un nom.', clickEnds: true },
    ],
    'changer-une-photo-accueil': [
        { path: 'content/hero-slides', element: table, title: 'Les grandes images de l\'accueil', text: 'Ce sont les images qui défilent en haut de la page d\'accueil.' },
        { path: 'content/hero-slides', element: rowButton('Modifier'), title: 'Changer une image', text: 'Cliquez ici pour changer la photo, le titre ou le texte du bouton.', clickEnds: true },
    ],
    'ajouter-un-look': [
        { path: 'content/looks', element: button('Créer'), title: 'Nouveau look', text: 'Un look montre une tenue portée, avec les produits à acheter. Cliquez ici pour en créer un.' },
        { path: 'content/looks/creer', element: section('Photo ou vidéo'), title: 'La photo ou la vidéo', text: 'Choisissez le type, puis déposez votre photo ou votre vidéo.' },
        { path: 'content/looks/creer', element: field('Titre'), title: 'Le titre', text: 'Donnez un nom au look.' },
        { path: 'content/looks/creer', element: submit, title: 'Enregistrer, puis ajouter les produits', text: 'Cliquez ici pour enregistrer. Ensuite, sur la fiche du look, la partie « Les pièces portées » permet d\'ajouter chaque produit et de placer son point sur l\'image : les clientes pourront cliquer dessus pour l\'acheter.' },
    ],
    'ecrire-un-article': [
        { path: 'content/posts', element: button('Créer'), title: 'Nouvel article', text: 'Le Journal sert à raconter : conseils, coulisses, nouveautés. Cliquez ici pour écrire.' },
        { path: 'content/posts/create', element: field('Titre'), title: 'Le titre', text: 'Une phrase courte qui donne envie de lire.' },
        { path: 'content/posts/create', element: field('Texte'), title: 'Le texte', text: 'Écrivez votre article ici.' },
        { path: 'content/posts/create', element: field('Photo de couverture'), title: 'La photo', text: 'Elle apparaît en haut de l\'article et dans le Journal.' },
        { path: 'content/posts/create', element: field('Publié'), title: 'Publier', text: 'Activez ceci quand l\'article est prêt. Sinon, il reste un brouillon invisible.' },
        { path: 'content/posts/create', element: submit, title: 'Enregistrer', text: SAVE },
    ],
    'gerer-les-avis': [
        { path: 'content/reviews', element: table, title: 'Les avis clients', text: 'Un avis envoyé par une cliente n\'apparaît pas tout seul : c\'est vous qui décidez.' },
        { path: 'content/reviews', element: rowButton('Modifier'), title: 'Publier un avis', text: 'Cliquez ici, puis activez « Publié (visible sur le site) ».', clickEnds: true, emptyText: 'Aucun avis pour le moment.' },
    ],
    'repondre-a-un-message': [
        { path: 'content/contact-messages', element: table, title: 'Les messages reçus', text: 'Ce que les visiteurs vous écrivent depuis le formulaire de contact.' },
        { path: 'content/contact-messages', element: row('/content/contact-messages/'), title: 'Lire un message', text: 'Cliquez sur un message pour l\'ouvrir.', emptyText: 'Aucun message pour le moment.' },
        { path: 'content/contact-messages/*', element: button('Répondre par email'), title: 'Répondre', text: 'Cliquez ici pour répondre par e-mail.' },
    ],
    'mes-coordonnees': [
        { path: 'site-settings', element: section('Coordonnées de contact'), title: 'Vos coordonnées', text: 'Votre WhatsApp, votre e-mail et votre adresse, affichés partout sur le site. Ils ne sont à changer qu\'ici.' },
        { path: 'site-settings', element: section('Pied de page'), title: 'Vos réseaux sociaux', text: 'Collez les liens de vos pages Instagram, Facebook et TikTok.' },
        { path: 'site-settings', element: section('Bandeau défilant'), title: 'Les phrases de l\'accueil', text: 'Les courts messages qui défilent sur le site.' },
        { path: 'site-settings', element: section('Paiement'), title: 'Le paiement à la livraison', text: 'Activez-le seulement si vous voulez le proposer aux clientes.' },
        { path: 'site-settings', element: section('Stock'), title: 'L\'alerte de stock', text: 'À partir de quelle quantité vous êtes prévenue par e-mail.' },
        { path: 'site-settings', element: submit, title: 'Enregistrer', text: 'N\'oubliez pas de cliquer ici après vos changements.' },
    ],
    'pages-legales': [
        { path: 'content/legal-pages', element: table, title: 'Vos pages légales', text: 'Les conditions de vente, la confidentialité, les mentions légales et les cookies. Une page non publiée reste invisible.' },
        { path: 'content/legal-pages', element: rowButton('Modifier'), title: 'Modifier une page', text: 'Cliquez ici pour relire ou changer le texte. Faites relire vos pages par un juriste avant de les publier.' },
    ],
    'trouver-une-cliente': [
        { path: 'clients', element: search, title: 'Chercher une cliente', text: 'Écrivez son nom ou son e-mail.' },
        { path: 'clients', element: table, title: 'Vos clientes', text: 'Pour chacune : ce qu\'elle a dépensé et son dernier achat. Les filtres permettent de retrouver les régulières, les inactives ou les paniers abandonnés.' },
        { path: 'clients', element: row('/clients/'), title: 'Ouvrir sa fiche', text: 'Cliquez pour voir ses achats, son panier et ses adresses.', emptyText: 'Aucune cliente pour le moment.' },
        { path: 'clients/*', element: section('Achats'), title: 'Ses achats', text: 'Le total dépensé et le panier moyen de cette cliente.' },
    ],
    'ajouter-un-membre': [
        { path: 'admin/admins', element: button('Créer'), title: 'Nouveau compte', text: 'Chaque personne de l\'équipe doit avoir son propre compte. Cliquez ici.' },
        { path: 'admin/admins/create', element: field('Nom'), title: 'Son nom', text: 'Il apparaîtra dans le journal d\'activité.' },
        { path: 'admin/admins/create', element: field('Email'), title: 'Son e-mail', text: 'Il lui servira à se connecter.' },
        { path: 'admin/admins/create', element: field('Rôle'), title: 'Ce qu\'elle peut faire', text: 'Le rôle « Gestionnaire » donne accès au catalogue, au stock et aux commandes. Réservez « Administrateur complet » aux personnes de confiance.' },
        { path: 'admin/admins/create', element: submit, title: 'Enregistrer', text: SAVE },
    ],
    'journal-activite': [
        { path: 'admin/admin-activities', element: table, title: 'Qui a fait quoi', text: 'Toutes les actions importantes de l\'équipe sont notées ici, avec la personne et la date. Rien ne peut être modifié ni effacé depuis cet écran.' },
    ],
};
