#!/usr/bin/env bash
# migrate.sh <env_file> <app_tag> [wait_for_db]
#
# Runs the pending migrations in a one-off container attached to the stack's
# overlay network (docker-compose.prod.yml declares the pre-created
# portfolio_prod network as external). The app image bakes its config at build
# time, so the env file is passed for parity only. On a first deploy the caller
# passes a third argument to wait for the freshly created Postgres service
# (pg_isready) before running the migrations.
set -euo pipefail

if [ "$#" -lt 2 ] || [ "$#" -gt 3 ]; then
    echo "usage: migrate.sh <env_file> <app_tag> [wait_for_db]" >&2
    exit 2
fi

env_file="$1"
app_tag="$2"
wait_for_db="${3:-false}"

set -a
. "$env_file"
set +a

if [ "$wait_for_db" = "true" ]; then
    deadline=$(( $(date +%s) + 300 ))
    until docker exec "$(docker ps -q -f name=portfolio_db | head -n 1)" \
        pg_isready -U "$DB_USERNAME" -d "$DB_DATABASE" >/dev/null 2>&1; do
        if [ "$(date +%s)" -ge "$deadline" ]; then
            echo "ERROR: portfolio_db did not become ready within 300s" >&2
            exit 1
        fi
        sleep 2
    done
fi

docker run --rm --network portfolio_prod \
    --env-file "$env_file" \
    "127.0.0.1:5000/portfolio-app:$app_tag" \
    php artisan migrate --force
