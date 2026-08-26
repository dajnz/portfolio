#!/bin/sh
set -e

CONF="/etc/nginx/conf.d/default.conf"
DOMAIN="${DOMAIN:-_}"

# Dev environments bind-mount the config read-only (docker-compose.localhost
# .yml) — the template processing below would fail on a read-only file, so
# dev sets NGINX_SKIP_TEMPLATE=1 (no <domain> placeholders, no certs).
if [ "${NGINX_SKIP_TEMPLATE:-0}" != "1" ]; then
    # Replace the <domain> placeholders in the (commented) HTTPS server blocks.
    sed -i "s|<domain>|${DOMAIN}|g" "$CONF"

    # Enable the HTTPS server blocks when Let's Encrypt certificates exist.
    if [ -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" ] && [ -f "/etc/letsencrypt/live/${DOMAIN}/privkey.pem" ]; then
        sed -i '/HTTPS-BLOCK-BEGIN/,/HTTPS-BLOCK-END/{
            /HTTPS-BLOCK-/s/.*//
            s/^# *//
        }' "$CONF"
    fi
fi

exec "$@"
