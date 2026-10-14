# Phase 4 — Livraison : journal d'avancement

**Statut : terminée.** Découpée en sous-étapes validées une par une avec Ahmed :
1. ✅ **4A** — zones de livraison (table, back-office, règle de calcul)
2. ✅ **4B** — frais auto dans le tunnel, figé à l'achat
3. ✅ **4C** — suivi manuel admin (livreur/note)
4. ✅ **4D** — suivi visible côté client
5. ✅ **4E** — contrat transporteur (Yango), seeder, revue sécurité

Référence du périmètre : [docs/files/04-livraison.md](../files/04-livraison.md).

## Ce qui a été construit

- **`delivery_zones`** : ville unique (null = défaut « Autres zones »), frais, délai, actif, ordre. `DeliveryZone::forCity()` insensible casse/accents, repli défaut, sinon frais 0 — le tunnel ne bloque jamais. Cache 1h, invalidé à chaque écriture (même pattern que les catégories).
- **Tunnel** : frais par adresse affichés + total en direct (Alpine, chiffres serveur) ; `shipping_total` + `shipping_zone` recalculés serveur et figés dans `OrderService`.
- **Manuel admin** : `delivery_assignee`/`delivery_note` + action « Organiser la livraison » sur la fiche commande ; historique des statuts déjà tracé en Phase 3.
- **Client** : stepper Nouvelle → Livrée (bandeau si annulée), bloc suivi livreur/note, emails de statut déjà branchés.
- **Contrat `DeliveryProvider`** (devis, dispo) : `ManualDeliveryProvider` (zones, toujours dispo) + `YangoDeliveryProvider` (inactif sans `YANGO_KEY`/`YANGO_CLIENT_ID`, timeout 5s, null en repli — jamais d'exception vers le tunnel) + `DeliveryManager` (prioritaire = premier dispo).
- **Seeders** : `DeliveryZoneSeeder` (Dakar/Pikine/Rufisque/défaut, tarifs indicatifs à valider avec la cliente), branché avant `OrderDemoSeeder`.

## Décisions prises avec Ahmed

- **Yango vérifié** (Delivery Business couvre le Sénégal, personnes morales) **mais non branché** : clés en libre-service inexistantes (demande à integration-support@yango.com). Le code est prêt, le branchement attend le compte business de la cliente — action de son côté.
- **Pas de statut de livraison séparé** : le cycle commande (préparation/expédiée/livrée) porte le suivi manuel V1, + livreur/note. Un vrai transporteur apportera ses propres statuts via webhook (prévu au contrat).
- **Pas de lien de suivi public** : tout le suivi est derrière `auth` + contrôle d'appartenance — pas de token à sécuriser.

## Revue sécurité de fin de phase

- Frais recalculé serveur (jamais depuis le client) ; zones sous guard `admin`.
- Suivi d'autrui : 403 testé (compte + facture).
- Secrets Yango uniquement en `.env` (`config/delivery.php`), jamais commités.
- Webhook : n/a (aucune intégration active) — à sécuriser (signature) le jour du branchement.

## Tests

`DeliveryZoneTest` (6), `DeliveryCheckoutTest` (3), `DeliveryManagerTest` (3), `OrderAdminTest` +1 assignation, `OrderHistoryTest` +1 suivi. Suite complète à rejouer avant livraison (`php artisan test`).
