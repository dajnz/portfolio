#!/usr/bin/env bash
# build-assets.sh <release_dir>
#
# Builds the client assets into <release_dir>/public/build before the image
# builds: the PHP image needs the Vite manifest at runtime and the nginx image
# serves the hashed files. The builder image combines Node with the pinned
# pnpm binary; the store and node_modules live in named volumes so the release
# directory stays free of install artifacts between deploys.
set -euo pipefail

if [ "$#" -ne 1 ]; then
    echo "usage: build-assets.sh <release_dir>" >&2
    exit 2
fi

release_dir="$1"
assets_dir="$release_dir/devops/docker/assets"

docker build --quiet \
    -f "$assets_dir/Dockerfile" \
    -t portfolio-assets-builder \
    "$assets_dir"

docker run --rm \
    -v "$release_dir":/app \
    -v portfolio_pnpm_store:/pnpm-store \
    -v portfolio_node_modules:/app/node_modules \
    -w /app \
    portfolio-assets-builder \
    sh -c 'pnpm install --frozen-lockfile --store-dir /pnpm-store && pnpm run build'
