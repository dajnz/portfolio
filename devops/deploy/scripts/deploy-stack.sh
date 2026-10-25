#!/usr/bin/env bash
# deploy-stack.sh <release_dir> <env_file> <app_tag> <node_tag> <nginx_tag> [extra_compose]
#
# Renders docker-compose.prod.yml with the production environment and the
# manifest image tags and deploys the portfolio stack. The production env is
# sourced first so the compose ${...} interpolation (db/redis/rustfs/nginx
# services) resolves from the profile env file. Only the variables the compose
# file references are substituted:
#   - ${VAR:-default} forms are sed-normalized to plain ${VAR} first
#     (envsubst cannot match them; every referenced key is exported below,
#     so the defaults are never needed);
#   - the $${...} healthcheck/entrypoint escapes stay untouched
#     (plain full-file envsubst would corrupt both).
# An optional extra compose file (the provided-TLS overlay) is rendered through
# the same pipeline and passed as an additional -c file. The rendered compose
# files are written to /opt/portfolio/.deploy/ and deployed.
set -euo pipefail

if [ "$#" -lt 5 ] || [ "$#" -gt 6 ]; then
    echo "usage: deploy-stack.sh <release_dir> <env_file> <app_tag> <node_tag> <nginx_tag> [extra_compose]" >&2
    exit 2
fi

release_dir="$1"
env_file="$2"
app_tag="$3"
node_tag="$4"
nginx_tag="$5"
extra_compose="${6:-}"

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

render() {
    sed -E 's/\$\{(APP_TAG|NODE_TAG|NGINX_TAG|INERTIA_SSR_URL):-[^}]*\}/${\1}/g' "$1" \
    | envsubst \
        '${APP_TAG}${NODE_TAG}${NGINX_TAG}${DOMAIN}${DB_DATABASE}${DB_USERNAME}${DB_PASSWORD}${REDIS_PASSWORD}${RUSTFS_ACCESS_KEY}${RUSTFS_SECRET_KEY}${INERTIA_SSR_URL}' \
        > "$2"
}

mkdir -p "$(dirname "$compose_output")"
render docker-compose.prod.yml "$compose_output"
deploy_files=(-c "$compose_output")

if [ -n "$extra_compose" ]; then
    extra_output="${COMPOSE_EXTRA_OUTPUT:-$(dirname "$compose_output")/docker-compose.extra.yml}"
    render "$extra_compose" "$extra_output"
    deploy_files+=(-c "$extra_output")
fi

docker stack deploy "${deploy_files[@]}" portfolio
