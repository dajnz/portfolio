#!/usr/bin/env bash
# build-php-images.sh <release_dir> <env_file> <app_tag> <queue_tag> <scheduler_tag>
#
# Builds the PHP-FPM image once (app/queue/scheduler share the same
# Dockerfile, devops.md "Image Manifest") and pushes it under all three
# repository names. The production env file is passed to the build as a
# BuildKit secret so `php artisan config:cache` — run at image build time —
# bakes the real DB/Redis/RustFS/APP_KEY configuration into the image (swarm
# `docker stack deploy` does not support env_file).
set -euo pipefail

if [ "$#" -ne 5 ]; then
    echo "usage: build-php-images.sh <release_dir> <env_file> <app_tag> <queue_tag> <scheduler_tag>" >&2
    exit 2
fi

release_dir="$1"
env_file="$2"
app_tag="$3"
queue_tag="$4"
scheduler_tag="$5"

cd "$release_dir"
set -a
. "$env_file"
set +a

# The env file is passed as a BuildKit secret and hashed into a build arg:
# secrets do not participate in the layer cache key, so the hash is what makes
# an env-only change rerun config:cache.
env_hash="$(sha256sum "$env_file" | cut -d' ' -f1)"

# The admin footer version: the deployed git tag when the checked-out ref is
# tagged, otherwise the short commit SHA. Baked into the cached config via the
# Dockerfile's APP_VERSION build arg (config:cache freezes env() values).
app_version="$(git describe --tags --exact-match 2>/dev/null \
    || git rev-parse --short HEAD 2>/dev/null \
    || printf '%s' "$app_tag")"

docker build \
    --pull \
    -f devops/docker/php/Dockerfile \
    --secret "id=app_env,src=$env_file" \
    --build-arg APP_VERSION="$app_version" \
    --build-arg APP_ENV_HASH="$env_hash" \
    -t "127.0.0.1:5000/portfolio-app:$app_tag" \
    .

docker tag "127.0.0.1:5000/portfolio-app:$app_tag" "127.0.0.1:5000/portfolio-queue:$queue_tag"
docker tag "127.0.0.1:5000/portfolio-app:$app_tag" "127.0.0.1:5000/portfolio-scheduler:$scheduler_tag"

docker push "127.0.0.1:5000/portfolio-app:$app_tag"
docker push "127.0.0.1:5000/portfolio-queue:$queue_tag"
docker push "127.0.0.1:5000/portfolio-scheduler:$scheduler_tag"
