# ADR-0027: Local development environment & onboarding

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: A7, B38, B39, B40, B41, B42, B43, B44, B45, B46, B171, B308, B310, B330, B331, B332, B333, B334, B335, B336, B337, B338, B339, B340, C7, C8, C9, C10
- **Supersedes:** —
- **Amended by:** ADR-0034 (Node packages moved from npm to pnpm; the `npm_cache` volume is gone)

## Context

Local development must run the full stack (nginx, php-fpm, Postgres, Redis, RustFS, SSR) in Docker with bind-mounted source, work on hosts whose user uid is not 1000, and reproduce the CI environment. The spec assumed uid 1000, pecl, and the MinIO Docker Hub image, so these details had to be corrected and made deterministic.

## Decision

- Nginx serves uploaded assets same-origin: `location /assets/ { proxy_pass http://rustfs:9000/portfolio/; }` (path-style, matching `use_path_style_endpoint`). The DB stores only `blog/...`-style paths; public URLs are `{APP_URL}/assets/{path}`; the `portfolio` bucket carries a public-read policy while `backups` stays private.
- Compose: app/queue/scheduler build from `context: .` + `dockerfile: devops/docker/php/Dockerfile`; nginx carries `networks: [default, traefik]` for TLS mode; the localhost override sets `networks: !override [default]` on nginx so it never creates or manages the external Traefik network.
- `postgres:18+` requires the volume at `/var/lib/postgresql` (major-version subdir), not the old `/var/lib/postgresql/data`.
- OPcache is pre-enabled in the official PHP 8.5 images and is not installed via `docker-php-ext-install`.
- `redis 6.3.0` and `xdebug 3.5.3` are built from pinned GitHub release tarballs (`phpredis/phpredis`, `xdebug/xdebug`) via `phpize`, replacing `pecl install` and the flaky pecl.php.net channel; any pecl installs run before bundled-ext installs.
- The dev-mode `app` (php-fpm) image installs Xdebug behind the `INSTALL_XDEBUG` build arg (default 0) so web requests can be step-debugged; `docker-compose.yml` passes `INSTALL_XDEBUG=1` and adds `extra_hosts: host.docker.internal:host-gateway` (native Linux Docker does not provide that name) so Xdebug can reach the IDE. Production (`docker-compose.prod.yml`) omits the arg and keeps the runtime image Xdebug-free. The Xdebug config is the shared `devops/docker/xdebug.ini`, used by both the `dev` and the dev-mode `app` images.
- The runtime user is `portfolio`, uid/gid parameterized via `ARG HOST_UID`/`ARG HOST_GID` (default 1000, created with `addgroup -g` + `adduser -u -G`); `docker-compose.yml` passes them as `build.args` and `user: "${HOST_UID:-1000}:${HOST_GID:-1000}"` on `queue`/`scheduler`; the Makefile exports `HOST_UID`/`HOST_GID` from `id -u`/`id -g`; `.env.example` documents them. All PHP processes (fpm, queue, scheduler, e2e/lighthouse serves) run as that uid so bind-mounted `storage/` writes stay group-consistent.
- The `composer_cache` named volume is seeded with correct ownership by the dev Dockerfile pre-creating `/home/portfolio/.composer` as `portfolio`; the pnpm store lives inside the repo (ADR-0034), so it needs no volume.
- The dev container runs as `USER portfolio` (`docker compose exec -u root dev` is the root escape hatch); the dev image bakes `git config --system --add safe.directory /var/www/html`.
- Dev env: `CACHE_STORE=redis` in `.env.example` (Laravel 13 defaults to the database store); the nginx healthcheck probes `http://127.0.0.1:80` (not `localhost`, which resolves only to IPv6 in the container).
- RustFS buckets are managed with a one-off `quay.io/minio/mc:RELEASE.2025-08-13T08-35-41Z` container on the `portfolio_dev` network (the RustFS image ships no `mc`); `create-rustfs-buckets` is idempotent and also provisions the separate `sail_backups` user with `readwrite`.
- Mode switching: `devops/scripts/switch-env-mode.sh <localhost|tls>` rewrites the paired `.env` values for `APP_URL`, `TRUSTED_PROXIES` and `PORTFOLIO_DEV_MODE`; `make up`/`make up-localhost`/`make setup` invoke it via `ENV_MODE`, and `make restart`/`make restart-localhost` restart the containers of the active mode.
- `make setup` is the single first-run command and provisions only the **localhost** environment (`ENV_MODE localhost`); TLS is reached later with `make up`. It runs `key:generate`, `migrate`, `admin:create`, bucket creation, `pnpm install --frozen-lockfile`, `pnpm run build`, `db:seed`, a real health settle (counting the `{{.Health}}` field) and creates the `portfolio_test` database.
- Test DB: `ensure-test-database` checks/creates `portfolio_test` (standalone `make db-for-testing`); parallel runs derive `portfolio_test_test_<n>` themselves.
- Admin lifecycle is seeder-free: `make admin-create [LOGIN= EMAIL= PASSWORD= FORCE=1]` wraps `admin:create`, and `make seed-fresh` chains it after `migrate:fresh --seed`; `make admin-reset-password` wraps `admin:reset-password`.
- `make switch-prod` / `make switch-dev` set `APP_ENV`/`APP_DEBUG`, run `php artisan optimize`/`optimize:clear`, and restart queue + scheduler using the running project's compose config files (from the `com.docker.compose.project.config_files` label), so both compose modes work.
- CI pins Node 24 (`actions/setup-node@v4`) to match the dev container; `test-e2e` runs the Laravel/SSR servers in the same step as Playwright (background processes die when a `run` step ends); commitlint is enforced in the lint job (`@commitlint/cli` + `config-conventional`, ESM config).
- `make lint` mirrors the CI lint job (pint, biome, eslint, stylelint non-blocking, spectral ×2, commitlint) with standalone `spectral`/`commitlint` targets; `commitlint` lints `main..HEAD` only when a local `main` exists.
- nginx template processing can be skipped with `NGINX_SKIP_TEMPLATE=1`, letting `docker-compose.localhost.yml` bind-mount `default.conf` read-only for dev; the prod image keeps the baked config.
- `config/l5-swagger.php` returns `[]` when `L5Swagger\Generator` is absent so the production image build (which excludes the require-dev package) does not fatal.

## Consequences

- Hosts with a uid other than 1000 work unconfigured through `make`, but the dev/php images must be rebuilt when `HOST_UID`/`HOST_GID` change.
- Volumes poisoned by pre-fix root-era runs need a one-time cleanup (`docker volume rm <project>_composer_cache`, or `docker compose down -v`).
- `make setup` docs and the embedded Makefile rendering must be kept in sync with the target; the broader Makefile rendering was noted as an older excerpt still to re-sync.
