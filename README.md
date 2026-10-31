# Aissatou Store

Boutique e-commerce de mode féminine (voiles et hijabs, foulards, cols, parfums, sacs à main, cadeaux), mono-vendeur, mobile-first, pour le Sénégal. Prix en FCFA, paiement en ligne (Wave, Orange Money, carte via PayTech) ; paiement à la livraison activable dans les réglages (coupé par défaut).

## Fonctionnalités

**Site client**
- Catalogue avec catégories, collections, matières, recherche et filtres AJAX, images WebP responsives
- Accueil éditorial : hero en carrousel (photo paysage plein écran au format 16:9 ; photo portrait sur la moitié droite, texte sur fond clair ; version téléphone dédiée), bandeau défilant facultatif, univers en mosaïque, nouveautés, « Le Look » (une tenue par photo ou vidéo de 30 s au plus, pièces mises en avant tour à tour, défilement automatique : une vidéo passe au look suivant à sa fin), « La Maison » (diaporama de 10 photos au plus, fondu et zoom lent, effet de profondeur), parcours « L'expérience Aissatou », avis clients avec note moyenne ; apparition douce au défilement et zoom des photos au survol (désactivés en « animations réduites »)
- Collections en chapitres (sommaire, photo, aperçu des pièces) et journal en magazine (article à la une, temps de lecture, partage WhatsApp)
- **Lookbook** (`/lookbook`) : tous les looks en plein écran, l'un après l'autre (défilement aimanté), qui s'enchaînent seuls comme des stories (fin de la vidéo, 7 s pour une photo ; barre de progression, bouton pause, pause automatique quand le panneau des pièces est ouvert), ouverture et fin éditoriales ; seule la vidéo à l'écran joue ; panneau « Les pièces du look » (ajout au panier, ou commande du look entier sur WhatsApp) ; une adresse par look (`/lookbook/lumiere-doree`) avec image d'aperçu pour le partage ; chapitres facultatifs (« Korité 2026 »…) ; l'accueil n'en montre que les looks choisis
- Filtre prix en saisie libre (de / à) ; guide « L'art d'offrir » dont les tranches de budget sont calculées sur les prix réels du catalogue
- Panier invité et compte (fusion à la connexion), liste d'envies, avis clients
- Sur téléphone, compte accessible depuis l'en-tête (à côté du menu) et boutons « Se connecter / Créer un compte » en tête du menu
- **Espace cliente** : salutation selon l'heure de son appareil, badge « Cliente fidèle », commande en cours avec frise de suivi datée, coups de cœur, profil à compléter, adresses (numéro sénégalais vérifié, position exacte facultative : GPS du téléphone confirmé sur une carte OpenStreetMap au repère déplaçable, ou lien Google/Apple Maps ; position hors Sénégal refusée), historique des commandes, déconnexion
- Page contact : WhatsApp, e-mail, formulaire, réseaux sociaux ; afficher/masquer le mot de passe sur tous les formulaires
- Tunnel de commande : récapitulatif, code promo, frais de livraison par zone calculés côté serveur, choix du paiement
- **Paiement en ligne PayTech** : redirection, page d'attente qui revérifie le statut côté serveur, nouvel essai en cas d'échec, annulation automatique (et remise en stock) des commandes impayées après 30 min
- **Page livreur sans compte** : lien signé de 3 jours, lié au livreur assigné ; « J'ai le colis, je pars » (→ expédiée) puis « Livrée », avec appel, WhatsApp et itinéraire vers la cliente ; plus aucune donnée de la cliente une fois la commande terminée. Page **« Ma tournée »** : lien personnel du livreur (seule son empreinte SHA-256 est gardée), toutes ses commandes en cours au même endroit, renvoyé ou révoqué depuis l'admin
- **Suivi de commande sur WhatsApp** (API WhatsApp Cloud de Meta), seulement si la cliente coche la case au checkout (décochée par défaut) : commande confirmée (paiement à la livraison) ou paiement confirmé (en ligne), expédiée, livrée, annulée ; rien pour un paiement en ligne abandonné. Envoi en file, sans jamais bloquer la commande
- **Liens WhatsApp pré-remplis** (gratuits, sans l'API) : fiche produit, panier (articles et total), page d'une commande (numéro)
- **Pages légales** (`/mentions-legales`, `/confidentialite`, `/cgv`, `/cookies`), liées dans le pied de page une fois publiées ; la page cookies affiche le registre des cookies par catégorie
- **Bandeau de consentement aux cookies**, prêt mais dormant : il n'apparaît que si un cookie soumis à accord (mesure d'audience, suivi technique, marketing) est déclaré ; « Tout refuser » au même niveau que « Tout accepter », choix par catégorie, lien « Gérer les cookies » ; les scripts concernés passent par `<x-consent category="mesure-audience">`

**Back-office (`/admin`, Filament)**
- Bouton « Voir le site » dans la barre du haut : la boutique s'ouvre dans un nouvel onglet
- **Tableau de bord** : chiffre d'affaires encaissé, commandes payées, panier moyen, taux de conversion (compteur de visites maison et anonyme, sans cookie ni IP stockée), paniers abandonnés, meilleures ventes, comparaison à la période précédente ; agrégats quotidiens pour rester rapide quel que soit l'historique
- **Pages légales** (menu « Pages légales ») : texte mis en forme, publié ou brouillon, modification tracée au journal d'activité ; repères (`{ninea}`, `{raison_sociale}`…) remplacés par l'identité légale des réglages, une valeur manquante s'affichant « à compléter ». Brouillons fournis, à relire avant publication
- **Cookies et traceurs** : registre des cookies (fournisseur, catégorie, finalité, durée, actif) ; ajouter un outil de mesure d'audience s'y fait sans toucher au code et réveille le bandeau de consentement
- Catalogue, contenu éditorial, réglages du site (paiement à la livraison activable, coupé par défaut ; identité légale, photo de connexion, photos de « La Maison » réordonnables par glisser-déposer, bandeau défilant masqué quand il est vide), zones de livraison, livreurs (internes ou services extérieurs, désactivables), messages reçus
- **Lookbook** (menu « Lookbook ») : photo, vidéo envoyée ou lien YouTube (Short ou vidéo ; image d'aperçu récupérée automatiquement, lecteur sans cookies publicitaires chargé seulement à l'écran ; vidéos de la boutique ou avec l'accord de leur créatrice), titre, phrase, texte d'ambiance, chapitre, adresse de partage, « Aussi sur l'accueil », pièces portées
- **Vidéos du Look** : 30 s maximum vérifiées à l'envoi, allégées en arrière-plan par ffmpeg (version web 720 px sans son, image d'aperçu tirée de la vidéo) ; le look n'apparaît qu'une fois la vidéo prête, son état est affiché en admin
- **Charte photo** : un format et un minimum par emplacement (refus des photos trop petites, badge « À remplacer »), point focal réglable avec aperçu de chaque recadrage
- Commandes : statuts avec transitions contrôlées et historique (admin ou livreur), facture PDF, organisation de la livraison (livreur choisi dans la liste, bouton « Envoyer au livreur » : WhatsApp pré-rempli depuis le numéro de la boutique avec l'itinéraire et le lien de sa page), bloc WhatsApp (accord de la cliente, messages envoyés et leur état : envoyé, reçu, lu, échec avec la raison)
- **Paiements** : une ligne par paiement en ligne ; la fiche donne le statut réel, la référence de transaction et tout le déroulement expliqué en français (journal immuable), vérification auprès du fournisseur ; onglet « Messages suspects » pour les notifications rejetées
- **Codes promo** : pourcentage ou montant fixe, validité, minimum d'achat, ciblage produits/catégories, plafond d'utilisations (compteur atomique, revalidation complète à la validation de la commande), utilisation rendue si la commande est annulée
- **Stock** : inventaire par catégorie, fiche par déclinaison avec son historique, ajustement manuel avec motif obligatoire, journal immuable des mouvements, alertes email de stock faible (seuil global ou par déclinaison) avec lien vers la fiche de l'article
- **Clients** : segmentation (régulières, panier abandonné, inactives), historique complet des achats, lecture seule et consultation tracée
- **Comptes admin et rôles** : admin complet ou gestionnaire (catalogue, stock, commandes), permissions revérifiées côté serveur sur chaque page et action, journal d'activité lisible (une phrase en clair par action : connexions, comptes, commandes, réglages)

**API** : Laravel Sanctum, endpoints versionnés sous `/api/v1` (lecture du catalogue, authentification par jeton), en prévision d'une app mobile.

## Avancement

| Phase | Contenu | État |
|---|---|---|
| 1 | Fondations (auth séparée client/admin, catalogue, API) | ✅ |
| 2 | Expérience client (catalogue, panier, compte, accueil éditable) | ✅ |
| 3 | Tunnel de commande + paiement à la livraison | ✅ |
| 4 | Livraison (zones, suivi manuel ; Yango prêt, inactif sans clés) | ✅ |
| 5 | Paiement en ligne PayTech (Wave, Orange Money, carte) | ✅ |
| 6 | Back-office avancé : rôles, stock, codes promo, statistiques, segmentation clients | ✅ |
| 7 | Finalisation et mise en production | ⏳ |

## Stack technique

- **Backend** : Laravel 13, PHP 8.4 (8.4.27 minimum, correctifs « php2shell »), organisation par domaine (`App\Models\Catalogue`, `Order`, `Delivery`…)
- **Base de données** : MySQL 8
- **Site client** : Blade, Alpine.js, Tailwind CSS (Vite)
- **Back-office** : Filament 3, guard `admin` totalement séparé du guard `web` des clients (table `admins`)
- **Rôles et permissions** : spatie/laravel-permission
- **PDF** : barryvdh/laravel-dompdf
- **Paiement en ligne** : PayTech (API REST + notifications IPN signées HMAC)
- **Messages WhatsApp** : API WhatsApp Cloud de Meta (modèles de messages validés par Meta, webhook signé `X-Hub-Signature-256`)

## Architecture

### Vue d'ensemble

```mermaid
flowchart TB
    cliente(["Cliente<br/>navigateur mobile"])
    gerante(["Gérante / admin"])
    appmobile(["App mobile<br/>(à venir)"])

    subgraph app["Application Laravel 13"]
        direction TB
        subgraph entrees["Points d'entrée"]
            shop["Site client<br/>Blade + Alpine.js<br/>Shop · Account · Auth"]
            filament["Back-office /admin<br/>Filament 3"]
            api["API /api/v1<br/>Sanctum"]
            ipn["IPN PayTech<br/>signature HMAC"]
            hook["Webhook WhatsApp<br/>signature Meta"]
        end

        subgraph metier["Services métier (app/Services)"]
            order["OrderService<br/>commande atomique, statuts"]
            cart["CartService · WishlistService"]
            promo["PromoService"]
            stock["StockService<br/>seul chemin du stock"]
            payment["PaymentService<br/>+ PaymentManager"]
            delivery["DeliveryManager"]
            stats["StatisticsService · VisitTracker"]
            audit["AdminAccountService · AdminAudit"]
        end

        subgraph fond["Arrière-plan"]
            queue["queue:work<br/>emails · messages WhatsApp"]
            sched["schedule:work<br/>payments:expire · stats:aggregate<br/>carts:detect-abandoned"]
        end
    end

    db[("MySQL 8<br/>modèles par domaine")]
    paytech["PayTech<br/>Wave · Orange Money · carte"]
    yango["Yango<br/>(inactif sans clés)"]
    smtp["Serveur mail"]
    meta["Meta<br/>API WhatsApp Cloud"]

    cliente --> shop
    gerante --> filament
    appmobile -.-> api

    shop --> cart & order & promo
    shop --> stats
    filament --> order & stock & promo & stats & audit
    api --> db
    ipn --> payment

    order --> stock & promo & payment & cart
    payment -->|"CodProvider / PayTechProvider"| paytech
    delivery -->|"Manual / Yango"| yango
    paytech -->|"notification"| ipn

    metier --> db
    order & stock -->|"mails en file"| queue
    order & payment -->|"WhatsApp si accord"| queue
    queue --> smtp
    queue -->|"modèles de messages"| meta
    meta -->|"reçu · lu · échec"| hook
    hook --> db
    sched --> order & payment & stats
```

Les contrôleurs restent minces : toute règle métier (prix, stock, promo, statut) passe par un service, recalculée côté serveur. Paiement et livraison sont derrière un contrat (`PaymentProvider`, `DeliveryProvider`) : ajouter un fournisseur ne touche pas au tunnel.

### Deux mondes d'authentification séparés

```mermaid
flowchart LR
    subgraph web["Guard web"]
        users[("users<br/>clientes")]
        site["Site client, espace cliente"]
    end
    subgraph admin["Guard admin"]
        admins[("admins")]
        bo["Back-office Filament"]
        roles["spatie/permission<br/>admin · gestionnaire"]
    end
    subgraph sanctum["Sanctum"]
        tokens["Jetons API"]
    end
    site --- users
    bo --- admins
    bo --- roles
    tokens --- users
```

### Parcours d'un paiement en ligne

```mermaid
sequenceDiagram
    autonumber
    actor C as Cliente
    participant S as Site (Checkout)
    participant O as OrderService
    participant P as PaymentService
    participant PT as PayTech
    participant Q as File d'attente

    C->>S: Valide la commande
    S->>O: Création atomique (recalcul, verrous stock, promo)
    O-->>S: Commande en attente de paiement
    S->>PT: Demande de paiement
    PT-->>C: Redirection vers Wave / OM / carte
    PT->>P: IPN signée (HMAC)
    P->>P: Confirmation idempotente + journal du paiement
    P->>Q: Emails cliente et boutique
    C->>S: Retour sur la page d'attente
    S->>PT: Revérifie le statut côté serveur
    S-->>C: Commande confirmée
    Note over O,P: Non payée après 30 min : payments:expire annule et remet en stock
```

## Démarrage local

```bash
composer install
npm install && npm run build      # ou npm run dev en développement
cp .env.example .env
php artisan key:generate
```

Renseigner au minimum dans `.env` : `DB_*` (MySQL) et `ADMIN_SEED_EMAIL` / `ADMIN_SEED_PASSWORD` (compte admin créé par le seeder).

```bash
php artisan migrate --seed        # inclut rôles/permissions, admin, catalogue et commandes de démo
php artisan storage:link          # images produit
PHP_CLI_SERVER_WORKERS=8 php artisan serve --no-reload
```

Processus d'arrière-plan à lancer à côté du serveur :

```bash
php artisan queue:work            # emails, messages WhatsApp, vidéos du Look
php artisan schedule:work         # tâches planifiées (impayés PayTech, agrégats statistiques, paniers abandonnés)
```

- Site client : `http://localhost:8000`
- Back-office : `http://localhost:8000/admin` (identifiants = `ADMIN_SEED_EMAIL` / `ADMIN_SEED_PASSWORD`)
- Clientes de démo : `aicha.demo@example.com` et `fatou.demo@example.com`, mot de passe `password` (créées par `OrderDemoSeeder`)

### Variables d'environnement propres au projet

| Variable | Rôle |
|---|---|
| `ADMIN_SEED_EMAIL`, `ADMIN_SEED_PASSWORD` | Premier compte admin (seeder) |
| `PAYTECH_API_KEY`, `PAYTECH_API_SECRET` | Clés PayTech. Vides = paiement en ligne masqué dans le tunnel |
| `PAYTECH_ENV` | `test` (sandbox) ou `prod` (exige un compte marchand activé par PayTech) |
| `PAYTECH_IPN_URL` | URL publique HTTPS de l'IPN si `APP_URL` n'est pas joignable depuis Internet |
| `PAYMENT_EXPIRE_MINUTES` | Délai avant annulation d'une commande payable en ligne non payée (défaut 30) |
| `TRUSTED_PROXIES` | IP des proxies de confiance (vide par défaut ; `127.0.0.1` derrière ngrok en local) |
| `YANGO_CLIENT_ID`, `YANGO_KEY` | Transporteur Yango. Vides = livraison manuelle uniquement |
| `FFMPEG_BINARY`, `FFPROBE_BINARY` | Chemins de ffmpeg / ffprobe pour les vidéos du Look (défaut : `ffmpeg`, `ffprobe`) |
| `WHATSAPP_ENABLED` | `true` active le suivi WhatsApp (et la case d'accord au checkout). `false` par défaut |
| `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_BUSINESS_ACCOUNT_ID` | Identifiants du numéro et du compte WhatsApp Business (app Meta) |
| `WHATSAPP_ACCESS_TOKEN` | Jeton d'accès Meta : jeton d'un utilisateur système en production (le jeton temporaire du tableau de bord expire en une à deux heures) |
| `WHATSAPP_APP_SECRET` | Clé secrète de l'app Meta, vérifie la signature des notifications du webhook |
| `WHATSAPP_VERIFY_TOKEN` | Jeton libre, à saisir aussi dans Meta lors de la déclaration du webhook |
| `WHATSAPP_API_VERSION`, `WHATSAPP_TEMPLATE_LANGUAGE` | Version de l'API Graph (ex. `v25.0`) et langue des modèles (`fr`) |

Les secrets ne vont que dans `.env`, jamais dans le dépôt.

### Tester le paiement en ligne en local

PayTech doit pouvoir joindre l'IPN en HTTPS, et le client doit revenir sur le site après paiement. Il faut donc exposer tout le site via un tunnel :

```bash
ngrok http 8000
```

Puis dans `.env` : `APP_URL=https://xxxx.ngrok-free.app`, `PAYTECH_IPN_URL=https://xxxx.ngrok-free.app/paiement/paytech/ipn`, `TRUSTED_PROXIES=127.0.0.1`, et `php artisan config:clear`. Naviguer ensuite sur l'URL ngrok (pas sur `localhost`).

En sandbox (`PAYTECH_ENV=test`), PayTech débite réellement 100 à 150 FCFA sur le compte payeur, quel que soit le montant de la commande.

Les images utilisent des adresses relatives (`/storage/…`) : elles s'affichent aussi bien en local que via ngrok, sans dépendre d'`APP_URL`.

### Tester WhatsApp en local (bac à sable Meta)

1. Créer une app Meta (cas d'usage WhatsApp) : Meta fournit un numéro de test qui peut écrire à 5 numéros vérifiés au maximum.
2. Créer dans le Gestionnaire WhatsApp les 5 modèles, catégorie Utilité, langue français, sous ces noms exacts : `commande_confirmee`, `paiement_confirme`, `commande_expediee`, `commande_livree`, `commande_annulee`. Variables et bouton « Suivre ma commande » (URL dynamique `…/compte/commandes/{{1}}`) : voir `App\Enums\Order\WhatsAppTemplate`.
3. Remplir les variables `WHATSAPP_*` dans `.env`, puis `php artisan config:clear`.
4. Avec le tunnel ngrok, déclarer le webhook dans l'app Meta : URL `https://xxxx.ngrok-free.dev/webhooks/whatsapp`, jeton = `WHATSAPP_VERIFY_TOKEN`, champ `messages` abonné.
5. Passer une commande en cochant la case WhatsApp : le message part via `queue:work`, son état apparaît dans la fiche commande du back-office.

## Déploiement

À chaque mise en production, après `php artisan migrate --force` :

```bash
php artisan db:seed --class=RolePermissionSeeder --force   # aligne rôles et permissions sur le code
```

Sans cette étape, aucun compte admin n'a accès au back-office : en cas de doute, l'accès est refusé.

Prévoir aussi un worker de file (`queue:work`, supervisé) et la tâche cron `* * * * * php artisan schedule:run`.

Vidéos du Look : `ffmpeg` et `ffprobe` installés sur le serveur (chemins réglables par `FFMPEG_BINARY` / `FFPROBE_BINARY`), sinon l'option « Vidéo » est désactivée en admin ; `upload_max_filesize` et `post_max_size` à 64M au moins dans le `php.ini` (vidéos jusqu'à 60 Mo).

Serveur : PHP 8.4.27 ou plus (`composer install` refuse une version plus ancienne, exposée aux CVE-2026-109618/109619), et `session.upload_progress.enabled=0` dans le `php.ini` (précaution recommandée pour CVE-2026-115560). L'envoi d'e-mails (`MAIL_*`) doit être configuré : une nouvelle cliente doit vérifier son adresse avant de commander.

WhatsApp en production : entreprise de la cliente vérifiée chez Meta, numéro de la boutique enregistré, moyen de paiement ajouté, jeton d'utilisateur système, nouveaux `WHATSAPP_APP_SECRET` / `WHATSAPP_VERIFY_TOKEN`, modèles recréés avec l'URL du site en production, webhook redéclaré sur le domaine définitif.

## Tests et qualité

```bash
php artisan test
./vendor/bin/pint --test          # style de code
```

Les tests tournent sur une base MySQL dédiée (`phpunit.xml` ne surcharge que `DB_CONNECTION` / `DB_DATABASE` ; les identifiants viennent de `.env`). Aucun appel réseau réel : PayTech et Meta (WhatsApp) sont simulés (`Http::fake`, `preventStrayRequests`).

GitHub Actions (`.github/workflows/ci.yml`) exécute à chaque push et PR sur `main` : Pint, migrations sur MySQL 8, suite de tests complète.

## Structure du projet

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── Shop/              site client et tunnel (accueil, catalogue, panier, commande, journal, contact)
│   │   ├── Account/           espace cliente (tableau de bord, commandes, adresses)
│   │   ├── Auth/              inscription, connexion, mot de passe (guard web)
│   │   ├── Payment/           IPN PayTech (signature HMAC)
│   │   ├── WhatsApp/          webhook Meta (vérification d'abonnement, statuts signés)
│   │   ├── Delivery/          pages du livreur sans compte : une commande (lien signé), « Ma tournée »
│   │   ├── Admin/             facture PDF
│   │   └── Api/V1/            catalogue et authentification par jeton (Sanctum)
│   └── Middleware/TrackVisit  compteur de visites anonyme
│
├── Services/                  logique métier : les contrôleurs et Filament passent par ici
│   ├── OrderService           création atomique (recalcul serveur, verrous anti-survente), transitions de statut
│   ├── StockService           seul chemin de variation du stock (journalisé), alertes de stock faible
│   ├── PromoService           aperçu et décision finale des codes promo (verrou + incrément conditionnel)
│   ├── CartService, WishlistService, AddressService
│   ├── StatisticsService, VisitTracker   tableau de bord et visites
│   ├── AdminAccountService, AdminAudit   comptes admin et journal d'activité
│   ├── ImageVariantGenerator  variantes WebP responsives (480 → 2 400 px)
│   ├── VideoProcessor         vidéos du Look via ffmpeg : durée, version web, image d'aperçu
│   ├── Payment/               contrat PaymentProvider → CodProvider, PayTechProvider ;
│   │                          PaymentManager (choix), PaymentService (confirmation idempotente)
│   ├── Delivery/              contrat DeliveryProvider → Manual, Yango ; DeliveryManager ; CourierService
│   │                          (actions du livreur, lien signé, message WhatsApp)
│   └── WhatsApp/              WhatsAppClient (envoi d'un modèle à l'API Cloud), WhatsAppException
│
├── Jobs/                      SendOrderWhatsApp (message de suivi en file : accord vérifié, journalisé,
│                              nouvelles tentatives sur panne passagère, jamais envoyé deux fois),
│                              ProcessLookVideo (vidéo du Look allégée en arrière-plan)
├── Models/                    par domaine
│   ├── Catalogue/             produits, déclinaisons, images, catégories, collections, matières, mouvements de stock
│   ├── Order/                 commandes, lignes, historique de statut, paiements, journal des paiements, codes promo,
│   │                          messages WhatsApp
│   ├── Cart/, Wishlist/, Customer/ (adresses), Delivery/ (zones, livreurs)
│   ├── Content/, Contact/     accueil (hero, « Le Look »), journal, avis, réglages, messages
│   ├── User                   clientes (guard web)
│   └── Admin, AdminActivity   comptes back-office (guard admin) et journal d'activité
│
├── Filament/                  back-office /admin
│   ├── Resources/             Catalogue, Order, Customer, Content, Delivery, Admin
│   ├── Clusters/              Paiements, Stock
│   ├── Pages/, Widgets/       tableau de bord, réglages du site, graphiques
│   ├── Forms/                 champs réutilisables (point focal avec aperçu des recadrages)
│   └── Concerns/              traits de contrôle d'accès par permission
│
├── Enums/                     statuts (commande, paiement, message WhatsApp…), modèles WhatsApp, permissions admin
├── Rules/                     SenegalPhone (numéro à 9 chiffres), MapsLink (lien Google/Apple Maps seulement)
├── Support/                   AdminRoles (rôles, le code fait foi), ImageSlot (charte photo : format, minimum,
│                              recadrage par emplacement), PhoneNumber (format international +221), DeliveryLocation
│                              (règles de la position de livraison, cadre du Sénégal),
│                              textes lisibles des journaux, périodes de stats
├── Mail/                      emails en file : commande, statut, alerte boutique, stock faible
├── Observers/                 variantes d'images à l'enregistrement du contenu, lancement du traitement vidéo
└── Console/Commands/          payments:expire (5 min), stats:aggregate (nuit, --from=AAAA-MM-JJ),
                               carts:detect-abandoned (heure), images:generate-variants

routes/        web.php · shop.php · account.php · auth.php · delivery.php (livreur) · api.php · console.php (planification)
resources/     views/ (shop, account, auth, emails, pdfs, filament…), css/, js/ (Tailwind, Alpine, Vite ; Leaflet chargé à la demande pour la carte de position)
database/      migrations, factories, seeders (rôles et permissions, admin, catalogue et commandes de démo)
tests/Feature/ par domaine : Shop, Account, Order, Payment, Delivery, Catalogue, Admin, Api, Auth, WhatsApp
```

La documentation interne (cahier des charges, journaux de phase) est conservée hors dépôt.
