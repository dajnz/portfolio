#!/bin/sh
set -e

CONF="/etc/nginx/conf.d/default.conf"
DOMAIN="${DOMAIN:-_}"

# Replace the <domain> placeholders in the (commented) HTTPS server blocks.
sed -i "s|<domain>|${DOMAIN}|g" "$CONF"

# Enable the HTTPS server blocks when Let's Encrypt certificates exist.
if [ -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" ] && [ -f "/etc/letsencrypt/live/${DOMAIN}/privkey.pem" ]; then
    sed -i '/HTTPS-BLOCK-BEGIN/,/HTTPS-BLOCK-END/{
        /HTTPS-BLOCK-/s/.*//
        s/^# *//
    }' "$CONF"
fi

exec "$@"
