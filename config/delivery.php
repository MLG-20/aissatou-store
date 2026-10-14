<?php

/**
 * Transporteurs (Phase 4). Yango reste INACTIF tant que `YANGO_KEY` et
 * `YANGO_CLIENT_ID` ne sont pas renseignés : compte Yango Delivery Business
 * de la cliente + clés demandées à integration-support@yango.com.
 */
return [
    'yango' => [
        'base_url' => env('YANGO_BASE_URL', 'https://delivery.yango.com/api'),
        'client_id' => env('YANGO_CLIENT_ID'),
        'key' => env('YANGO_KEY'),
    ],
];
