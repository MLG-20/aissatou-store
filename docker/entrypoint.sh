#!/bin/sh
# Prépare les caches avec la configuration du conteneur, puis lance sa commande.
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

exec "$@"
