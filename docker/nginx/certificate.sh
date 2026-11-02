#!/bin/sh
# Choisit le certificat au démarrage : celui de Let's Encrypt s'il existe, sinon
# un certificat local, pour que Nginx démarre avant la première demande.
set -e

mkdir -p /run/site-cert

if [ -f /etc/letsencrypt/live/site/fullchain.pem ]; then
    ln -sf /etc/letsencrypt/live/site/fullchain.pem /run/site-cert/fullchain.pem
    ln -sf /etc/letsencrypt/live/site/privkey.pem /run/site-cert/privkey.pem
else
    openssl req -x509 -nodes -newkey rsa:2048 -days 30 \
        -keyout /run/site-cert/privkey.pem -out /run/site-cert/fullchain.pem \
        -subj "/CN=${SERVER_NAME}" -addext "subjectAltName=DNS:${SERVER_NAME}" 2>/dev/null
fi
