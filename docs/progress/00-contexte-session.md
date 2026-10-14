# Contexte de session — Aissatou Store

**But de ce fichier :** permettre de reprendre le travail dans une nouvelle session sans rien perdre.
En début de session, lire ce fichier PUIS les docs listées au §2.

## 1. Projet en une ligne

E-commerce mode féminine (Sénégal), mobile-first. Laravel 12 / PHP 8.4, MySQL 8,
Blade + Alpine.js (public), Filament `/admin` (guard `admin` séparé, table `admins`),
API Sanctum `/api/v1`, spatie/laravel-permission.

## 2. Docs à lire impérativement (ordre)

1. `docs/univers-des-amiras-cahier-des-charges.md` — vision globale + phases 1 à 7
2. `docs/progress/01-fondations.md` — phase 1 terminée
3. `docs/progress/02-experience-client.md` — phase 2 (sous-étapes 1 à 8)
4. `docs/progress/02.1-reidentite-aissatou-store.md` — ré-identité visuelle (en cours)
5. `docs/progress/02.1-audit-conformite-pdf.md` — audit page par page + plan de correction (§7)
6. `docs/files/02.2-modules-maquette.md` — phase 2.2 spécifiée, non commencée
7. `docs/files/03-commande-paiement.md` — PROCHAINE PHASE à attaquer
8. `docs/Aissatou_Store_Rapport_Identite_Visuelle.pdf` — référentiel visuel (remplace `docs/files/ux-ui-direction-amiras.md`, archivé)

## 3. État d'avancement (2026-10-14)

- ✅ Phase 1 : terminée et validée.
- ✅ Phase 2 : terminée (catalogue, panier invité+compte, recherche/filtres AJAX, comptes/adresses, WebP/srcset/cache, accueil + vitrine éditable, avis, tiroir panier). Suite : 97 tests.
- ✅ Phase 2.1 : restes terminés au commit `5703a3e` — migration `rebrand_site_settings_to_aissatou_store` (À propos au positionnement officiel p.2), `x-shop.signature` sur pages auth (`layouts/guest.blade.php`).
- ⏳ Phase 2.2 : spécifiée, non commencée (Matière → Collections → Wishlist → Journal, dans cet ordre).
- ⏳ Phase 3 (tunnel commande + paiement à la livraison) : EN COURS (2026-10-14).
  - ✅ (A) Domaine : `orders`, `order_items` (prix figés), `payments`, `order_status_history` ; enums `OrderStatus`/`PaymentStatus` ; contrat `PaymentProvider` + `CodProvider` + `PaymentManager` ; `OrderService` atomique (recalcul serveur, verrous anti-survente, n° `AS-AAAA-XXXXXX`) — 9 tests verts.
  - ✅ (B) Tunnel checkout (compte requis) : `GET/POST /commande` (récap + adresse + paiement), `GET /commande/merci/{n°}` (anti-IDOR) — 5 tests verts.
  - ✅ (C) Notifications queued : `OrderPlaced` + `NewOrderForAdmin` à la création, `OrderStatusChanged` à chaque transition. Piège : ne JAMAIS nommer une prop de mailable `$to` (collision fatale avec `Mailable::$to`).
  - ⏳ (D) Back-office Filament commandes + facture PDF (`barryvdh/laravel-dompdf` installé).
  - ⏳ (E) Compte client : historique + détail commandes.

## 4. Décisions actives d'Ahmed (ne pas remettre en cause sans lui)

- Maquette cliente fait foi (même contre un principe général du rapport).
- Prix en FCFA partout (les EUR de la maquette sont un artefact).
- Typographies libres Fraunces/Inter (pas de licences Canela/Neue Haas).
- Nouveauté = label admin `Nouveauté` (`Product::scopeNewArrivals()`), jamais `latest()`.
- Pas de lien mort : Collections/Journal/cœur pointent vers `shop.coming-soon` jusqu'à la 2.2.
- Sacs à main conservé dans l'en-tête (demande cliente) même si hors maquette.
- Hero photographique conservé (cliente) malgré l'annotation 04 p.10.

## 5. Bloquants externes (cliente)

- Logos SVG officiels (`logo_horizontal.svg`, `logo_symbole_seul.svg`, `favicon.svg`) jamais transmis — symbole PNG provisoire, favicon PNG fallback en place. Interdit de redessiner (p.14).
- Visuels de marques tierces en base + catégories sans image — à arbitrer avec elle.
- Grille tarifaire démo (~16 000–72 000 FCFA après conversion) à valider.

## 6. Environnement local

```bash
php artisan serve --no-reload --host=127.0.0.1 --port=8000  # --no-reload + PHP_CLI_SERVER_WORKERS=8 requis pour les uploads
```
- Site : `http://localhost:8000` — Admin : `/admin` (identifiants = `ADMIN_SEED_EMAIL` / `ADMIN_SEED_PASSWORD` dans `.env`).
- Base : MySQL `univers_des_amiras` (identifiants dans `.env`, user `ahmed`). Tests sur base dédiée via `phpunit.xml`.
- Pièges connus : `public/storage` était un lien cassé vers l'ancien dossier `Projets_professionnels/` — réparé par `php artisan storage:link`. Après toute nouvelle classe Tailwind : `php artisan view:clear` puis `npm run build` (sinon CSS gonflé d'anciennes classes).
- Vérifications : `php artisan test` (~6 min, génération d'images), `./vendor/bin/pint --test`.

## 7. Dernière session (2026-10-14)

Réparé `public/storage`, fini les 2 restes 2.1, pint vert, tests auth 13/13. Serveur relancé.
**Suite : attaquer Phase 3** selon `docs/files/03-commande-paiement.md`.
