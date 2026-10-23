<?php

/**
 * Paiements électroniques (Phase 5). PayTech reste INACTIF tant que
 * `PAYTECH_API_KEY` et `PAYTECH_API_SECRET` ne sont pas renseignés — secrets
 * uniquement dans `.env`, jamais commités.
 *
 * `PAYTECH_ENV=test` : sandbox (PayTech débite réellement 100 à 150 FCFA,
 * quel que soit le montant). `prod` exige l'activation du compte marchand.
 */
return [
    // Délai pour payer une commande électronique ; au-delà, elle est annulée
    // et le stock remis en vente (décision Ahmed, 2026-10-23).
    'expire_minutes' => (int) env('PAYMENT_EXPIRE_MINUTES', 30),

    'paytech' => [
        'base_url' => env('PAYTECH_BASE_URL', 'https://paytech.sn/api'),
        'api_key' => env('PAYTECH_API_KEY'),
        'api_secret' => env('PAYTECH_API_SECRET'),
        'env' => env('PAYTECH_ENV', 'test'),
        'timeout' => (int) env('PAYTECH_TIMEOUT', 10),
        // URL publique de l'IPN si l'application n'est pas joignable via
        // APP_URL (tunnel ngrok en local). Vide = route `payment.paytech.ipn`.
        'ipn_url' => env('PAYTECH_IPN_URL'),
    ],
];
