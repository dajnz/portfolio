#!/usr/bin/env bash
set -euo pipefail

if [[ -f /.dockerenv ]]; then
    exec "$@"
fi

exec docker compose exec -T dev "$@"
