<?php

/**
 * WhatsApp Business Cloud API (Meta) : suivi de commande envoyé au client,
 * en plus de l'e-mail. Spécification : docs/files/08-whatsapp-cloud-api.md.
 *
 * INACTIF tant que `WHATSAPP_ENABLED` n'est pas vrai ET que le numéro, le
 * jeton et le secret ne sont pas renseignés — secrets uniquement dans `.env`,
 * jamais commités. Bac à sable Meta : mêmes variables, valeurs de l'app de
 * test ; la production ne change que ces valeurs.
 */
return [
    'enabled' => (bool) env('WHATSAPP_ENABLED', false),

    'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
    // Version de l'API Graph : à aligner sur la version courante de Meta.
    'api_version' => env('WHATSAPP_API_VERSION', 'v23.0'),
    'timeout' => (int) env('WHATSAPP_TIMEOUT', 10),

    // Numéro expéditeur et compte WhatsApp Business (WABA).
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    // Jeton permanent d'un « utilisateur système » Meta.
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),

    // Webhook : le secret de l'app signe chaque appel de Meta
    // (X-Hub-Signature-256) ; le verify token, choisi par nous, sert à
    // l'abonnement.
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),

    // Langue des modèles de messages validés par Meta.
    'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'fr'),
];
