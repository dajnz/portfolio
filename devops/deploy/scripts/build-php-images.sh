#!/usr/bin/env bash
# build-php-images.sh <release_dir> <env_file> <app_tag> <queue_tag> <scheduler_tag>
#
# Builds the PHP-FPM image once (app/queue/scheduler share the same
# Dockerfile, devops.md "Image Manifest") and pushes it under all three
# repository names. The production env file is sourced into the build
# environment: the image runs `php artisan config:cache` at build time,
# which freezes env() values, and swarm `docker stack deploy` does not
# support env_file — the bake is the only carrier for DB/Redis/RustFS/
# APP_KEY configuration into the app containers.
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

docker build \
    -f devops/docker/php/Dockerfile \
    -t "127.0.0.1:5000/portfolio-app:$app_tag" \
    .

docker tag "127.0.0.1:5000/portfolio-app:$app_tag" "127.0.0.1:5000/portfolio-queue:$queue_tag"
docker tag "127.0.0.1:5000/portfolio-app:$app_tag" "127.0.0.1:5000/portfolio-scheduler:$scheduler_tag"

docker push "127.0.0.1:5000/portfolio-app:$app_tag"
docker push "127.0.0.1:5000/portfolio-queue:$queue_tag"
docker push "127.0.0.1:5000/portfolio-scheduler:$scheduler_tag"
