.PHONY: setup up down up-localhost down-localhost restart restart-localhost health build install lefthook migrate seed seed-fresh build-frontend dev-frontend pictures custom-icons lang-export open-dev open-db open-redis admin-create admin-reset-password switch-prod switch-dev cache-clear db-for-testing test test-unit test-int test-backend test-frontend test-e2e lighthouse coverage coverage-backend coverage-frontend lint lint\:css spectral commitlint format typecheck quick-check secrets-init secrets-init-test deploy deploy-test rollback

DC = docker compose
DE = $(DC) exec dev
DC_LOCALHOST = $(DC) -f docker-compose.yml -f docker-compose.localhost.yml
DE_LOCALHOST = $(DC_LOCALHOST) exec dev

# The deploy/rollback targets prompt for the sudo password on the target host.
# Set ASK_BECOME=0 when the target user has passwordless sudo.
ASK_BECOME ?= 1
BECOME_FLAGS = $(if $(filter 1,$(ASK_BECOME)),--ask-become-pass,)

# Containers that write to the bind-mounted repo (dev/queue/scheduler/app)
# run as the host user. Auto-detecting uid/gid (compose interpolates exported
# vars over .env) makes the stack work on any host — a hardcoded uid 1000
# breaks e.g. a fresh clone owned by a VM user with a different uid.
export HOST_UID = $(shell id -u)
export HOST_GID = $(shell id -g)

# The rustfs image does not ship the MinIO `mc` client, so bucket creation
# runs a one-off MinIO mc container on the compose network, using the RustFS
# credentials from the project .env (same approach as the CI e2e job).
# Pinned on quay.io: MinIO removed the `minio/mc` Docker Hub repository
# (pulls now fail with "access denied" — ADR-0027).
MC = quay.io/minio/mc:RELEASE.2025-08-13T08-35-41Z

# The `portfolio` bucket gets a public-read ("download") policy — public asset
# URLs are served same-origin via nginx `/assets/ → rustfs:9000/portfolio/` and must be publicly readable.
# The `backups` bucket stays private (encrypted archives) and the app writes it with the separate
# `RUSTFS_BACKUPS_*` credentials, so that user is provisioned here as well.
define create-rustfs-buckets
	@set -e; \
	KEY="$$(sed -n 's/^RUSTFS_ACCESS_KEY=//p' .env)"; \
	SECRET="$$(sed -n 's/^RUSTFS_SECRET_KEY=//p' .env)"; \
	BACKUP_KEY="$$(sed -n 's/^RUSTFS_BACKUPS_ACCESS_KEY=//p' .env)"; \
	BACKUP_SECRET="$$(sed -n 's/^RUSTFS_BACKUPS_SECRET_KEY=//p' .env)"; \
	export MC_HOST_local="http://$${KEY}:$${SECRET}@rustfs:9000"; \
	docker run --rm --network portfolio_dev -e MC_HOST_local $(MC) mb --ignore-existing local/portfolio || true; \
	docker run --rm --network portfolio_dev -e MC_HOST_local $(MC) mb --ignore-existing local/backups || true; \
	docker run --rm --network portfolio_dev -e MC_HOST_local $(MC) anonymous set download local/portfolio || true; \
	docker run --rm --network portfolio_dev -e MC_HOST_local $(MC) admin user add local "$${BACKUP_KEY}" "$${BACKUP_SECRET}" || true; \
	docker run --rm --network portfolio_dev -e MC_HOST_local $(MC) admin policy attach local readwrite --user "$${BACKUP_KEY}" || true
endef

# .env stores each mode-dependent variable twice — the active value and the
# other mode's value commented (localhost is the default; see .env.example).
# The mode entry points rewrite the pairs via this script, so switching
# between the localhost and TLS dev modes is a single command.
ENV_MODE = ./devops/scripts/switch-env-mode.sh

# Initial setup always provisions the localhost environment
# (no Traefik/DNS required); switch to TLS mode later with `make up` (see devops.md).
setup:
	cp -n .env.example .env || true
	@$(ENV_MODE) localhost
	$(DC_LOCALHOST) build
	$(DC_LOCALHOST) up -d
	@echo "Waiting for all services to become healthy..."
	@while [ $$($(DC_LOCALHOST) ps --format '{{.Health}}' | grep -c '^starting$$') -gt 0 ]; do \
		sleep 3; \
	done
	@echo "All services healthy:"
	@$(DC_LOCALHOST) ps -a --format 'table {{.Name}}\t{{.Status}}'
	$(DE_LOCALHOST) composer install
	$(DE_LOCALHOST) pnpm install --frozen-lockfile
	$(DE_LOCALHOST) pnpm exec lefthook install
	$(DE_LOCALHOST) pnpm run build
	$(DE_LOCALHOST) php artisan key:generate
	$(DE_LOCALHOST) php artisan migrate
	$(DE_LOCALHOST) php artisan admin:create
	$(create-rustfs-buckets)
	$(DE_LOCALHOST) php artisan db:seed
	$(DE_LOCALHOST) php artisan backup:generate-key --name "initial"
	@echo "Waiting for services to settle..."
	@i=0; \
	while [ "$$i" -lt 20 ] && [ "$$($(DC_LOCALHOST) ps --format '{{.Health}}' | grep -cE '^(unhealthy|starting)$$')" -gt 0 ]; do \
		sleep 3; \
		i=$$((i + 1)); \
	done
	@$(DC_LOCALHOST) ps -a --format 'table {{.Name}}\t{{.Status}}'
	$(ensure-test-database)

# Start dev containers (TLS mode — requires Traefik + DNS)
up:
	@$(ENV_MODE) tls
	$(DC) up -d

# Stop and remove containers
down:
	$(DC) down

# Start dev containers in localhost mode (no Traefik — http://localhost:8888)
up-localhost:
	@$(ENV_MODE) localhost
	$(DC_LOCALHOST) up -d

# Stop and remove localhost mode containers
down-localhost:
	$(DC_LOCALHOST) down

# Restart all containers
restart: down up

# Restart all localhost mode containers
restart-localhost: down-localhost up-localhost

# Show service health status (all services must be healthy before proceeding)
health:
	@echo "Service health status:"
	@$(DC) ps -a --format 'table {{.Name}}\t{{.Status}}'

# Build Docker images
build:
	$(DC) build --pull

# Install PHP + JS dependencies and the git hooks
install:
	$(DE) composer install
	$(DE) pnpm install --frozen-lockfile
	$(DE) pnpm exec lefthook install

# Install the git hooks (pre-commit, commit-msg, pre-merge-commit) that lefthook runs
lefthook:
	$(DE) pnpm exec lefthook install

# Run database migrations
migrate:
	$(DE) php artisan migrate

# Seed demo content (no admin credentials)
seed:
	$(DE) php artisan db:seed

# Wipe and re-seed the whole dev database, then recreate the admin account
seed-fresh:
	$(DE) php artisan migrate:fresh --seed
	$(MAKE) --no-print-directory admin-create

# Rebuild the production front-end assets into public/build
# (needed after Vue/TS changes when the Vite dev server is NOT running)
build-frontend:
	$(DE) pnpm run build

# Start the Vite dev server (HMR — front-end changes apply automatically while it runs)
dev-frontend:
	$(DE) pnpm run dev

# Prepare image assets: optimize/minify resources/images -> public/images + section favicons
pictures:
	$(DE) node devops/assets/prepare-images.mjs

# Regenerate the custom icon subset from resources/icons/custom/*.svg
# (add an SVG and run this — the file name becomes the icon id, e.g. messenger.svg -> custom:messenger)
custom-icons:
	$(DE) pnpm run icons:custom

# Regenerate the frontend translation catalogs (resources/js/lang/{en,ru}.json)
# from the PHP sources in lang/{en,ru}/ — run after editing the PHP strings
lang-export:
	$(DE) php artisan lang:export

# Open an interactive shell in the dev container
open-dev:
	$(DE) bash

# Open an interactive psql session in the db container
open-db:
	$(DC) exec db sh -c 'psql -U "$$POSTGRES_USER" -d "$$POSTGRES_DB"'

# Open an interactive redis-cli against the dev Redis (sessions/cache/rate limits)
open-redis:
	$(DC) exec redis redis-cli

# Create the initial admin account
# (prompts interactively; optional overrides: LOGIN=<admin> EMAIL=<email> PASSWORD=<password> FORCE=1)
admin-create:
	@ARGS=""; \
	if [ -n "$(LOGIN)" ]; then ARGS="$$ARGS --login=$(LOGIN)"; fi; \
	if [ -n "$(EMAIL)" ]; then ARGS="$$ARGS --email=$(EMAIL)"; fi; \
	if [ -n "$(PASSWORD)" ]; then ARGS="$$ARGS --password=$(PASSWORD)"; fi; \
	if [ -n "$(FORCE)" ]; then ARGS="$$ARGS --force"; fi; \
	$(DE) php artisan admin:create $$ARGS

# Reset an admin password
# (prompts interactively; optional overrides: LOGIN=<admin> PASSWORD=<new-password>)
admin-reset-password:
	@ARGS=""; \
	if [ -n "$(LOGIN)" ]; then ARGS="$$ARGS --login=$(LOGIN)"; fi; \
	if [ -n "$(PASSWORD)" ]; then ARGS="$$ARGS --password=$(PASSWORD)"; fi; \
	$(DE) php artisan admin:reset-password $$ARGS

# The `switch-*` targets flip the app between development and production mode on the local machine
# (both compose modes — TLS `make up` and `make up-localhost` — are supported). They:
# (1) rewrite APP_ENV/APP_DEBUG in .env (bind-mounted into every container),
# (2) bake or clear the Laravel caches (`php artisan optimize` / `optimize:clear`),
# (3) restart the persistent env-consuming services
# (queue worker + scheduler; php-fpm re-reads .env per request and serves the cached config after
# `optimize`). The restart reuses the compose config files the running
# project was started with, so the active TLS/localhost mode is preserved.
define restart-persistent-services
	@CTR="$$($(DC) ps -q app 2>/dev/null | head -n1)"; \
	if [ -z "$$CTR" ]; then \
		echo "No running app container found — skipped the queue/scheduler restart."; \
	else \
		FLAGS=""; \
		for f in $$(docker inspect "$$CTR" --format '{{index .Config.Labels "com.docker.compose.project.config_files"}}' 2>/dev/null | tr ',' ' '); do \
			FLAGS="$$FLAGS -f $$f"; \
		done; \
		docker compose $$FLAGS restart queue scheduler; \
	fi
endef

# Switch the portfolio to production mode locally
# (APP_ENV=production/APP_DEBUG=false in .env + php artisan optimize + queue/scheduler restart)
switch-prod:
	@sed -i 's/^APP_ENV=.*/APP_ENV=production/; s/^APP_DEBUG=.*/APP_DEBUG=false/' .env
	$(DE) php artisan optimize
	$(restart-persistent-services)
	@echo "Production mode: APP_ENV=production, APP_DEBUG=false, caches baked."

# Switch the portfolio back to development mode
# (APP_ENV=local/APP_DEBUG=true in .env + php artisan optimize:clear + queue/scheduler restart)
switch-dev:
	@sed -i 's/^APP_ENV=.*/APP_ENV=local/; s/^APP_DEBUG=.*/APP_DEBUG=true/' .env
	$(DE) php artisan optimize:clear
	$(restart-persistent-services)
	@echo "Development mode: APP_ENV=local, APP_DEBUG=true, caches cleared."

# Clear the Redis cache (resets the feedback rate-limit counters for testing; sessions/queues survive)
cache-clear:
	$(DE) php artisan cache:clear

# The backend suites run against the dedicated `portfolio_test` database (phpunit.xml)
# the db container only creates the dev database, so ensure
# it exists first (CI's postgres service creates it via POSTGRES_DB).
# The parallel runs derive their own `portfolio_test_test_<n>` databases.
define ensure-test-database
	@DB_USER="$$(sed -n 's/^DB_USERNAME=//p' .env)"; \
	EXISTS="$$($(DC) exec -T db psql -U "$$DB_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='portfolio_test'" | tr -d '[:space:]')"; \
	if [ "$$EXISTS" != "1" ]; then \
		echo "Creating test database portfolio_test..."; \
		$(DC) exec -T db createdb -U "$$DB_USER" portfolio_test; \
	fi
endef

# Create the dedicated backend test database (portfolio_test) if missing
db-for-testing:
	$(ensure-test-database)

# Run all test suites except e2e (backend + frontend)
test:
	$(DE) env XDEBUG_MODE=off php artisan test --parallel --recreate-databases --drop-databases --processes=8
	$(DE) pnpm exec vitest run

# Run unit tests (backend + frontend)
test-unit:
	$(DE) env XDEBUG_MODE=off php artisan test tests/unit/backend/
	$(DE) pnpm exec vitest run tests/unit/frontend/

# Run integration tests (backend + frontend)
test-int:
	$(DE) env XDEBUG_MODE=off php artisan test tests/integration/backend/ --parallel --recreate-databases --drop-databases --processes=8
	$(DE) pnpm exec vitest run tests/integration/frontend/

# Run all backend tests (unit + integration)
test-backend:
	$(DE) env XDEBUG_MODE=off php artisan test --parallel --recreate-databases --drop-databases --processes=8

# Run all frontend tests (unit + integration)
test-frontend:
	$(DE) pnpm exec vitest run

# The e2e suite runs against a DEDICATED database (`portfolio_e2e`) and an isolated Redis DB (1),
# served by `php artisan serve` on 127.0.0.1:8001 inside the dev container
# (same pattern as CI, devops.md:1097) — the dev database and the nginx stack are never touched.
# `E2ETestSeeder` still truncates + reseeds on every run (via global-setup), but only the e2e DB.
test-e2e:
	@set -e; \
	DB_USER="$$(sed -n 's/^DB_USERNAME=//p' .env)"; \
	EXISTS="$$($(DC) exec -T db psql -U "$$DB_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='portfolio_e2e'" | tr -d '[:space:]')"; \
	if [ "$$EXISTS" != "1" ]; then \
		echo "Creating e2e database portfolio_e2e..."; \
		$(DC) exec -T db createdb -U "$$DB_USER" portfolio_e2e; \
	fi; \
	$(DE) env DB_DATABASE=portfolio_e2e REDIS_DB=1 php artisan migrate --force; \
	$(DE) sh -c 'pkill -f "[p]hp -S .*8001" 2>/dev/null; pkill -f "[p]hp artisan serve.*8001" 2>/dev/null; true'; \
	$(DE) sh -c 'rm -f /tmp/e2e-serve.log'; \
	$(DC) exec -u $(HOST_UID) -d dev sh -c 'env DB_DATABASE=portfolio_e2e REDIS_DB=1 php artisan serve --no-reload --host=0.0.0.0 --port=8001 >/tmp/e2e-serve.log 2>&1'; \
	trap '$(DE) sh -c "pkill -f \"[p]hp artisan serve.*8001\"; pkill -f \"[p]hp -S .*8001\"; true"' EXIT; \
	$(DE) sh -c 'pnpm exec wait-on -t 30000 http://127.0.0.1:8001/up || (echo "e2e server failed:"; cat /tmp/e2e-serve.log; exit 1)'; \
	$(DE) env DB_DATABASE=portfolio_e2e REDIS_DB=1 PLAYWRIGHT_BASE_URL=http://127.0.0.1:8001 pnpm exec playwright test

# The Lighthouse budget + Core Web Vitals spot check
# runs the 6 canonical public routes against the dedicated e2e database on 127.0.0.1:8002
# (same isolation pattern as test-e2e; the dev DB and the nginx stack are untouched).
lighthouse:
	@set -e; \
	DB_USER="$$(sed -n 's/^DB_USERNAME=//p' .env)"; \
	EXISTS="$$($(DC) exec -T db psql -U "$$DB_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='portfolio_e2e'" | tr -d '[:space:]')"; \
	if [ "$$EXISTS" != "1" ]; then \
		echo "Creating e2e database portfolio_e2e..."; \
		$(DC) exec -T db createdb -U "$$DB_USER" portfolio_e2e; \
	fi; \
	$(DE) env DB_DATABASE=portfolio_e2e REDIS_DB=1 php artisan migrate --force; \
	$(DE) env DB_DATABASE=portfolio_e2e REDIS_DB=1 php artisan db:seed --class=E2ETestSeeder --force; \
	$(DE) sh -c 'pkill -f "[p]hp -S .*8002" 2>/dev/null; pkill -f "[p]hp artisan serve.*8002" 2>/dev/null; true'; \
	$(DE) sh -c 'rm -f /tmp/lighthouse-serve.log'; \
	$(DC) exec -u $(HOST_UID) -d dev sh -c 'env DB_DATABASE=portfolio_e2e REDIS_DB=1 php artisan serve --no-reload --host=0.0.0.0 --port=8002 >/tmp/lighthouse-serve.log 2>&1'; \
	trap '$(DE) sh -c "pkill -f \"[p]hp artisan serve.*8002\"; pkill -f \"[p]hp -S .*8002\"; true"' EXIT; \
	$(DE) sh -c 'pnpm exec wait-on -t 30000 http://127.0.0.1:8002/up || (echo "lighthouse server failed:"; cat /tmp/lighthouse-serve.log; exit 1)'; \
	$(DE) env LH_URLS="http://127.0.0.1:8002/,http://127.0.0.1:8002/blog,http://127.0.0.1:8002/blog/understanding-laravel-queues,http://127.0.0.1:8002/projects,http://127.0.0.1:8002/projects/portfolio-platform,http://127.0.0.1:8002/experience" pnpm run lighthouse

# Run all test suites with coverage (80% line coverage threshold, per testing spec)
coverage:
	$(DE) env XDEBUG_MODE=coverage php artisan test --coverage --min=80
	$(DE) pnpm exec vitest run --coverage

# Run backend tests with coverage (80% line coverage threshold)
coverage-backend:
	$(DE) env XDEBUG_MODE=coverage php artisan test --coverage --min=80

# Run frontend tests with coverage (80% line coverage threshold)
coverage-frontend:
	$(DE) pnpm exec vitest run --coverage

# Lint all code (mirrors the CI lint job: pint, biome, eslint, stylelint, spectral, commitlint)
lint:
	$(DE) ./vendor/bin/pint --test
	$(DE) pnpm exec biome ci resources/js
	$(DE) pnpm exec eslint resources/js
	$(DE) pnpm exec stylelint "resources/js/**/*.vue" || true
	$(DE) pnpm exec spectral lint docs/openapi/openapi.yaml --ruleset .spectral.yaml
	$(DE) pnpm exec spectral lint docs/openapi/inertia/inertia.yaml --ruleset .spectral.yaml
	@MAIN="$$(git rev-parse --verify -q main 2>/dev/null)"; \
	if [ -n "$$MAIN" ] && [ "$$MAIN" != "$$(git rev-parse HEAD)" ]; then \
		$(DE) pnpm exec commitlint --from main --to HEAD; \
	fi

# Validate CSS in Vue <style> blocks (non-blocking)
lint\:css:
	$(DE) pnpm exec stylelint "resources/js/**/*.vue" || true

# Lint both OpenAPI specs with the project ruleset (.spectral.yaml)
spectral:
	$(DE) pnpm exec spectral lint docs/openapi/openapi.yaml --ruleset .spectral.yaml
	$(DE) pnpm exec spectral lint docs/openapi/inertia/inertia.yaml --ruleset .spectral.yaml

# Lint commit messages (conventional commits; current branch vs main)
commitlint:
	@MAIN="$$(git rev-parse --verify -q main 2>/dev/null)"; \
	if [ -n "$$MAIN" ] && [ "$$MAIN" != "$$(git rev-parse HEAD)" ]; then \
		$(DE) pnpm exec commitlint --from main --to HEAD; \
	fi

# Auto-format code
format:
	$(DE) ./vendor/bin/pint
	$(DE) pnpm exec biome check --write --unsafe resources/js

# TypeScript type check (includes API + Inertia type generation)
typecheck:
	$(DE) pnpm run typecheck

# Runs all fast tests and checks
quick-check: test lint typecheck

# Generate production secrets and encrypt .env.prod.local
secrets-init:
	$(DE) ./devops/secrets/init.sh .env.prod.local .env.prod.age production

# Generate the vm-test deployment env (provided TLS; set DOMAIN in .env.prod.test.local)
secrets-init-test:
	$(DE) ./devops/secrets/init.sh .env.prod.test.local .env.prod.test.age vm-test

# Deploy to production (usage: make deploy GIT_REF=<tag-or-commit>)
deploy:
	@[ -n "$(GIT_REF)" ] || (echo "ERROR: GIT_REF is required. Usage: make deploy GIT_REF=<tag-or-commit>" && exit 1)
	ansible-playbook $(BECOME_FLAGS) -i devops/deploy/hosts.yml devops/deploy/deploy.yml -e "git_ref=$(GIT_REF) deploy_profile=production"
	# On first deploy the playbook reads AGE_PRIVATE_KEY from the environment
	# to provision /opt/portfolio/.env.prod (see secrets-management.md)

# Deploy the vm-test profile (usage: make deploy-test GIT_REF=<tag-or-commit>)
# TLS is provided by the operator at /opt/portfolio/tls/live/<DOMAIN>/{fullchain,privkey}.pem
deploy-test:
	@[ -n "$(GIT_REF)" ] || (echo "ERROR: GIT_REF is required. Usage: make deploy-test GIT_REF=<tag-or-commit>" && exit 1)
	ansible-playbook $(BECOME_FLAGS) -i devops/deploy/hosts.yml devops/deploy/deploy.yml -e "git_ref=$(GIT_REF) deploy_profile=vm-test"
	# The first deploy reads AGE_PRIVATE_KEY to provision /opt/portfolio/.env.prod.test
	# from the local .env.prod.test.age (see devops/deploy/README.md)

# Rollback deployed services (usage: make rollback [SERVICE=<name>])
rollback:
	ansible-playbook $(BECOME_FLAGS) -i devops/deploy/hosts.yml devops/deploy/rollback.yml -e "service=$(or $(SERVICE),all)"
