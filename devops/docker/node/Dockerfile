# Stage 1: Build SSR bundle
FROM node:24-alpine AS build

WORKDIR /var/www/html
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run ssr:build

# Stage 2: Production runtime — only SSR bundle + runtime deps
FROM node:24-alpine

RUN adduser -D -u 1000 node
WORKDIR /var/www/html
COPY --from=build --chown=node:node /var/www/html/bootstrap/ssr/ssr.js ./bootstrap/ssr/ssr.js
COPY --from=build --chown=node:node /var/www/html/node_modules ./node_modules
