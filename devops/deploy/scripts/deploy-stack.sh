#!/usr/bin/env bash
# deploy-stack.sh <release_dir> <env_file> <app_tag> <node_tag> <nginx_tag>
#
# Renders docker-compose.prod.yml with the production environment and the
# manifest image tags and deploys the portfolio stack. The production env is
# sourced first so the compose ${...} interpolation (db/redis/rustfs/nginx
# services) resolves from /opt/portfolio/.env.prod. Only the variables the
# compose file references are substituted:
#   - ${VAR:-default} forms are sed-normalized to plain ${VAR} first
#     (envsubst cannot match them; every referenced key is exported below,
#     so the defaults are never needed);
#   - the $${...} healthcheck/entrypoint escapes stay untouched
#     (plain full-file envsubst would corrupt both).
# The rendered compose is written to /opt/portfolio/.deploy/ and deployed.
set -euo pipefail

if [ "$#" -ne 5 ]; then
    echo "usage: deploy-stack.sh <release_dir> <env_file> <app_tag> <node_tag> <nginx_tag>" >&2
    exit 2
fi

release_dir="$1"
env_file="$2"
app_tag="$3"
node_tag="$4"
nginx_tag="$5"

# COMPOSE_OUTPUT overrides the rendered-compose destination (used by tests;
# the playbook relies on the default /opt/portfolio/.deploy path).
compose_output="${COMPOSE_OUTPUT:-/opt/portfolio/.deploy/docker-compose.prod.yml}"

cd "$release_dir"
set -a
. "$env_file"
set +a
export APP_TAG="$app_tag"
export NODE_TAG="$node_tag"
export NGINX_TAG="$nginx_tag"

mkdir -p "$(dirname "$compose_output")"
sed -E 's/\$\{(APP_TAG|NODE_TAG|NGINX_TAG|INERTIA_SSR_URL):-[^}]*\}/${\1}/g' \
    docker-compose.prod.yml \
| envsubst \
    '${APP_TAG}${NODE_TAG}${NGINX_TAG}${DOMAIN}${DB_DATABASE}${DB_USERNAME}${DB_PASSWORD}${REDIS_PASSWORD}${RUSTFS_ACCESS_KEY}${RUSTFS_SECRET_KEY}${INERTIA_SSR_URL}' \
    > "$compose_output"

docker stack deploy -c "$compose_output" portfolio
