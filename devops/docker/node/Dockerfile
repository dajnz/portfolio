# Stage 1: Build SSR bundle
FROM node:24-alpine AS build

# pnpm is pinned in package.json `packageManager`. The binary is copied from
# the official musl image so the build does not depend on the base image's npm.
COPY --from=ghcr.io/pnpm/pnpm:12.15.0-alpine /opt/pnpm /opt/pnpm
RUN ln -s /opt/pnpm/pnpm /usr/local/bin/pnpm

WORKDIR /var/www/html
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
# Keep the store out of the workdir layer; /tmp and the workdir share the
# container filesystem, so packages are still hard-linked.
RUN pnpm install --frozen-lockfile --store-dir /tmp/pnpm-store
COPY . .
RUN pnpm run ssr:build

# Stage 2: Production runtime — only SSR bundle + runtime deps
FROM node:24-alpine

WORKDIR /var/www/html
COPY --from=build --chown=node:node /var/www/html/bootstrap/ssr ./bootstrap/ssr
COPY --from=build --chown=node:node /var/www/html/node_modules ./node_modules
