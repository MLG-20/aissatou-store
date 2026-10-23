<?php

/**
 * Proxies de confiance (lu par le middleware TrustProxies) : leurs en-têtes
 * X-Forwarded-* (https, hôte, IP client) sont crus. Vide = aucun.
 *
 * En local derrière ngrok : `TRUSTED_PROXIES=127.0.0.1` (l'agent ngrok se
 * connecte depuis la machine). En prod : l'IP du reverse proxy / load
 * balancer, jamais `*` sans raison — sinon IP client falsifiable (journal
 * des paiements, limites de débit).
 */
return [
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
