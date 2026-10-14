# Aissatou Store

Boutique e-commerce de mode féminine (voiles/hijabs, parfums, sacs à main, collants, vêtements), mono-vendeur, mobile-first, ciblant le Sénégal.

Le cahier des charges complet et le détail de chaque phase se trouvent dans [docs/](docs/univers-des-amiras-cahier-des-charges.md). L'avancement réel (ce qui est fait, les décisions prises et pourquoi) est documenté phase par phase dans [docs/progress/](docs/progress/) — c'est le premier endroit à consulter pour reprendre le contexte du projet.

## Stack technique

- **Backend** : Laravel 12 (PHP 8.4), architecture modulaire par domaine métier (`App\Models\Catalogue`, etc.)
- **Base de données** : MySQL 8
- **Site public (client)** : Blade + Alpine.js
- **Back-office admin** : [Filament](https://filamentphp.com/) (panel `/admin`), guard d'authentification `admin` totalement séparé du guard `web` des clients (table `admins` dédiée)
- **API** : Laravel Sanctum, endpoints versionnés sous `/api/v1`, pensée pour une future app mobile
- **Rôles/permissions** : spatie/laravel-permission

## Démarrage local

```bash
composer install
npm install && npm run build   # ou npm run dev en développement
cp .env.example .env
php artisan key:generate
```

Configurer dans `.env` : `DB_*` (MySQL), `ADMIN_SEED_EMAIL` / `ADMIN_SEED_PASSWORD` (compte admin créé par le seeder).

```bash
php artisan migrate --seed
php artisan storage:link       # nécessaire pour les images produit
php artisan serve
```

- Site public : `http://localhost:8000`
- Back-office admin : `http://localhost:8000/admin` (identifiants = `ADMIN_SEED_EMAIL` / `ADMIN_SEED_PASSWORD`)
- Comptes clients de démo (commandes de tous statuts) : `aicha.demo@example.com` / `fatou.demo@example.com`, mot de passe `password` (créés par `OrderDemoSeeder`, inclus dans `migrate --seed`)

## Tests

```bash
php artisan test
./vendor/bin/pint --test   # style de code
```

Les tests tournent sur une base MySQL dédiée (`DB_DATABASE` défini dans `phpunit.xml` en surchargeant uniquement `DB_CONNECTION`/`DB_DATABASE` — les identifiants viennent de `.env`, jamais commités).

## Intégration continue

GitHub Actions (`.github/workflows/ci.yml`) exécute à chaque push/PR sur `main` : style de code (Pint), migrations sur MySQL, suite de tests complète.

## Structure du projet

- `app/Models/Catalogue/` — modèles du domaine catalogue (Catégorie, Produit, Variante, Image, Matière, Collection)
- `app/Models/Wishlist/` — liste d'envies (compte + session, fusion à la connexion)
- `app/Models/Content/` — contenu éditable (Hero, Réglages, Avis, Articles du journal)
- `app/Models/Contact/` — messages reçus via le formulaire Contact (suivi lu/non-lu en admin)
- `app/Models/Order/` — modèles du domaine commande (Commande, Ligne, Paiement, Historique statuts)
- `app/Models/Delivery/` — zones de livraison (frais par ville + défaut, cache 1h)
- `app/Services/Delivery/` — contrat `DeliveryProvider`, `ManualDeliveryProvider`, `YangoDeliveryProvider` (inactif sans clés), `DeliveryManager`
- `app/Services/OrderService.php` — création atomique de commande (recalcul serveur, anti-survente) + transitions gardées
- `app/Services/Payment/` — contrat `PaymentProvider`, `CodProvider` (livraison), `PaymentManager`
- `app/Mail/` — `OrderPlaced`, `OrderStatusChanged`, `NewOrderForAdmin` (queued ; worker requis : `php artisan queue:work`)
- `app/Http/Controllers/Shop/` — contrôleurs du site public (catalogue, panier, tunnel `commande`)
- `app/Http/Controllers/Account/` — espace client (adresses, historique et détail commandes)
- `app/Http/Controllers/Admin/` — facture PDF d'une commande (guard `admin` uniquement)
- `app/Http/Controllers/Api/V1/` — API publique (lecture catalogue) et authentification par token
- `app/Filament/Resources/` — ressources du back-office admin (dont `Order` : liste/filtres, fiche, changement de statut tracé)
- `routes/web.php`, `routes/shop.php`, `routes/auth.php` — routes du site public
- `routes/api.php` — routes API v1
- `docs/files/0X-*.md` — cahier des charges détaillé par phase
- `docs/progress/0X-*.md` — journal d'avancement réel par phase (mis à jour au fil du développement)
