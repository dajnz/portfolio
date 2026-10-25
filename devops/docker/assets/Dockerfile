FROM node:24-alpine

# pnpm is a standalone Node script; the official pnpm image does not ship Node
# itself, so the client-asset builder combines the two the same way the dev and
# node images do.
COPY --from=ghcr.io/pnpm/pnpm:12.15.0-alpine /opt/pnpm /opt/pnpm
RUN ln -s /opt/pnpm/pnpm /usr/local/bin/pnpm

WORKDIR /app
