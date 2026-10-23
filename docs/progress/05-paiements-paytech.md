# Phase 5 — Paiements électroniques (PayTech) : journal d'avancement

**Statut : en cours.** Découpée en sous-étapes validées une par une avec Ahmed :
1. ✅ **5A** — domaine : statut « en cours », journal `payment_events`, `config/payment.php`, contrat en deux temps
2. ✅ **5B** — `PayTechProvider` (demande de paiement, timeout, montant serveur)
3. ✅ **5C** — IPN (HMAC, montant, idempotence, suspects journalisés) + vérification active `get-status`
4. ✅ **5D** — tunnel client (redirection, attente, échec + réessayer) + annulation auto des impayés — essai réel sandbox validé
5. ⏳ **5E** — back-office (référence, journal, « Vérifier auprès de PayTech »), tests checklist, revue sécurité

Référence du périmètre : [docs/files/05-paiements-electroniques.md](../files/05-paiements-electroniques.md).

## Pourquoi PayTech

Un seul agrégateur sénégalais (paytech.sn) couvre Wave, Orange Money, Free Money et carte : un fournisseur au contrat au lieu de deux intégrations directes et deux comptes marchands.

## Décisions prises avec Ahmed (2026-10-23)

- **Impayés** : une commande PayTech non payée sous 30 min (`PAYMENT_EXPIRE_MINUTES`) est annulée automatiquement et son stock remis en vente.
- **Email boutique** (`NewOrderForAdmin`) : pour PayTech, envoyé seulement une fois le paiement confirmé (pas de bruit avec les abandons). Paiement à la livraison inchangé.
- **IPN en local** : via le ngrok d'Ahmed (`PAYTECH_IPN_URL`).
- **Annulation = remise en stock**, aussi pour l'annulation admin (corrige un manque de la Phase 3).

## Ce qui a été construit

- **5A** : `PaymentStatus::Processing` (`en_cours`) ; colonnes `payments.token/method/confirmed_at/expires_at` ; table `payment_events` + modèle `PaymentEvent` immuable (update/delete lèvent une exception) + `Payment::log()` ; enum `PaymentEventType` ; `config/payment.php` (secrets `.env` uniquement) ; contrat `PaymentProvider` scindé — `initiate()` dans la transaction (aucun appel réseau, verrous de stock), `checkout()` après commit (URL de redirection ou null) ; `PaymentException` (messages client sans détail technique).

- **5B** : `PayTechProvider` (`isAvailable` = clés présentes) enregistré dans `PaymentManager`. `ref_command` = `{n° commande}-{tentative}` (PayTech refuse une ref déjà vue, 409 constaté en sandbox). Montant = `payments.amount` (calcul serveur). Timeout 10 s, connexion 5 s, 2 nouvelles tentatives sur erreur réseau seulement.
- **5C** : `PaymentService` = seul point de changement d'état (verrou `lockForUpdate` + relecture → idempotent ; email boutique une seule fois, après commit). `POST /paiement/paytech/ipn` (hors CSRF, throttle 60/min) : signature → commande connue → `item_price` = total serveur → traitement ; tout rejet journalisé `notification_suspecte` avec IP. HMAC = confirmation directe ; empreintes SHA-256 seules = rejouables, donc confirmation uniquement via `get-status`. Contrat enrichi : `isOnline()`, `verify()`.
- **5D** : après commit, `checkout()` → redirection PayTech ; `success_url` et `cancel_url` → `GET /commande/paiement/{n°}` qui revérifie chez PayTech (auto-rafraîchie 5 s) ; `POST` même URL = réessayer (vérifie d'abord qu'une tentative précédente n'a pas abouti). `/commande/merci` refuse un paiement en ligne non confirmé. Commande `payments:expire` (toutes les 5 min, `routes/console.php`) : interroge PayTech puis annule + remet en stock. Page « Merci » adaptée (payée en ligne vs à la livraison).

## Points d'attention

- **Sandbox** : `env=test` débite réellement 100 à 150 FCFA sur le compte payeur, quel que soit le montant.
- **IPN** : HTTPS public obligatoire. HMAC documenté = `sha256_hmac(final_item_price|ref_command|api_key, api_secret)` — doc PayTech incohérente (`item_price` vs `final_item_price`) : on vérifie avec le payload réel du sandbox.
- **`get-status`** (relevé sandbox) : `{"success":1,"payment":{"state":"running",…}}` ; payé → `"state":"success"` (+ `buyer_*`, `date_checkout`) ; jeton inconnu → `{"success":0,"payment":null}`.
- **Essai réel validé (2026-10-23, `AS-2026-52XULF`, Orange Money)** : IPN reçu via ngrok avec `hmac_compute` valide (formule `final_item_price|ref_command|api_key`, `final_item_price` = `item_price` = 13000 ici), confirmation + email boutique unique, page « Merci » affichée. Commande abandonnée `AS-2026-ER2PXP` annulée seule après vérification `get-status`, stock rendu.
- **Frais PayTech** : la page de paiement affiche +2 % à la charge du payeur (13 260 pour 13 000) — réglage du compte marchand, à arbitrer avec la cliente.
- **ngrok** : tout le site passe par le tunnel (`APP_URL` = URL ngrok, `TRUSTED_PROXIES=127.0.0.1` via `config/trustedproxy.php`), sinon CSS en http bloqué et retour PayTech vers localhost.
- **Planificateur** : `payments:expire` exige `php artisan schedule:work` en local, cron `schedule:run` en prod.
- **Double paiement possible** si le client paie deux tentatives : les deux IPN sont journalisés, remboursement manuel via PayTech.
- **Paiement après expiration** : le paiement passe « confirmé », la commande reste « annulée » → à traiter par l'admin (journal).

## Tests

`PaymentEventTest` (4), `PayTechTest` (13), `OrderServiceTest` +1 remise en stock. `ContactMessageAdminTest` corrigé (assertait encore `wa.me` direct, obsolète depuis `/whatsapp`).
