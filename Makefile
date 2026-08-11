.PHONY: up down up-localhost down-localhost restart build build-frontend dev-frontend install open-db migrate seed setup setup-localhost test test-unit test-int test-backend test-frontend test-e2e coverage coverage-backend coverage-frontend lint lint\:css spectral commitlint format typecheck health secrets-init deploy rollback

DC = docker compose
DE = $(DC) exec dev
DC_LOCALHOST = $(DC) -f docker-compose.yml -f docker-compose.localhost.yml
DE_LOCALHOST = $(DC_LOCALHOST) exec dev

# The rustfs image does not ship the MinIO `mc` client, so bucket creation
# runs a one-off minio/mc container on the compose network, using the RustFS
# credentials from the project .env (same approach as the CI e2e job).
# The `portfolio` bucket gets a public-read ("download") policy — public asset
# URLs are served same-origin via nginx `/assets/ → rustfs:9000/portfolio/`
# (assumption A7) and must be anonymously readable. The `backups` bucket
# stays private (encrypted archives).
define create-rustfs-buckets
	@set -e; \
	KEY="$$(sed -n 's/^RUSTFS_ACCESS_KEY=//p' .env)"; \
	SECRET="$$(sed -n 's/^RUSTFS_SECRET_KEY=//p' .env)"; \
	export MC_HOST_local="http://$${KEY}:$${SECRET}@rustfs:9000"; \
	docker run --rm --network portfolio_dev -e MC_HOST_local minio/mc:latest mb --ignore-existing local/portfolio || true; \
	docker run --rm --network portfolio_dev -e MC_HOST_local minio/mc:latest mb --ignore-existing local/backups || true; \
	docker run --rm --network portfolio_dev -e MC_HOST_local minio/mc:latest anonymous set download local/portfolio || true
endef

up: ## Start dev containers (TLS mode — requires Traefik + DNS)
	$(DC) up -d

down: ## Stop and remove containers
	$(DC) down

up-localhost: ## Start dev containers in localhost mode (no Traefik — http://localhost:8888)
	$(DC_LOCALHOST) up -d

down-localhost: ## Stop and remove localhost mode containers
	$(DC_LOCALHOST) down

restart: down up ## Restart all containers

build: ## Build Docker images
	$(DC) build

build-frontend: ## Rebuild the production front-end assets into public/build (needed after Vue/TS changes when the Vite dev server is NOT running — without public/hot Laravel serves this bundle)
	$(DE) npm run build

dev-frontend: ## Start the Vite dev server (HMR — front-end changes apply automatically while it runs; keep the terminal open)
	$(DE) npm run dev

health: ## Show service health status (all services must be healthy before proceeding)
	@echo "Service health status:"
	@$(DC) ps -a --format 'table {{.Name}}\t{{.Status}}'

install: ## Install PHP + JS dependencies
	$(DE) composer install
	$(DE) npm ci

open-db: ## Open an interactive psql session in the db container
	$(DC) exec db sh -c 'psql -U "$$POSTGRES_USER" -d "$$POSTGRES_DB"'

migrate: ## Run database migrations
	$(DE) php artisan migrate

seed: ## Seed demo content (no admin credentials)
	$(DE) php artisan db:seed

setup: ## First-run setup: env, build, wait for healthy, install, migrate, admin
	cp -n .env.example .env || true
	$(DC) build
	$(DC) up -d
	@echo "Waiting for all services to become healthy..."
	@while [ $$($(DC) ps -q --filter "health=starting" | wc -l) -gt 0 ]; do \
		sleep 3; \
	done
	@echo "All services healthy:"
	@$(DC) ps -a --format 'table {{.Name}}\t{{.Status}}'
	$(DE) composer install
	$(DE) npm ci
	$(DE) php artisan key:generate
	$(DE) php artisan migrate
	$(DE) php artisan admin:create
	$(create-rustfs-buckets)
	$(DE) php artisan backup:generate-key --name "initial"

setup-localhost: ## First-run setup in localhost mode (no Traefik required)
	cp -n .env.example .env || true
	@# Override APP_URL for localhost mode
	sed -i 's|^APP_URL=.*|APP_URL=http://localhost:8888|' .env
	$(DC_LOCALHOST) build
	$(DC_LOCALHOST) up -d
	@echo "Waiting for all services to become healthy..."
	@while [ $$($(DC_LOCALHOST) ps -q --filter "health=starting" | wc -l) -gt 0 ]; do \
		sleep 3; \
	done
	@echo "All services healthy:"
	@$(DC_LOCALHOST) ps -a --format 'table {{.Name}}\t{{.Status}}'
	$(DE_LOCALHOST) composer install
	$(DE_LOCALHOST) npm ci
	$(DE_LOCALHOST) php artisan key:generate
	$(DE_LOCALHOST) php artisan migrate
	$(DE_LOCALHOST) php artisan admin:create
	$(create-rustfs-buckets)
	$(DE_LOCALHOST) php artisan backup:generate-key --name "initial"

test: ## Run all test suites except e2e (backend + frontend)
	$(DE) env XDEBUG_MODE=off php artisan test --parallel --recreate-databases --drop-databases --processes=8
	$(DE) npx vitest run

test-unit: ## Run unit tests (backend + frontend)
	$(DE) env XDEBUG_MODE=off php artisan test tests/unit/backend/
	$(DE) npx vitest run tests/unit/frontend/

test-int: ## Run integration tests (backend + frontend)
	$(DE) env XDEBUG_MODE=off php artisan test tests/integration/backend/ --parallel --recreate-databases --drop-databases --processes=8
	$(DE) npx vitest run tests/integration/frontend/

test-backend: ## Run all backend tests (unit + integration)
	$(DE) env XDEBUG_MODE=off php artisan test --parallel --recreate-databases --drop-databases --processes=8

test-frontend: ## Run all frontend tests (unit + integration)
	$(DE) npx vitest run

# The e2e suite runs against a DEDICATED database (`portfolio_e2e`) and an
# isolated Redis DB (1), served by `php artisan serve` on 127.0.0.1:8001
# inside the dev container (same pattern as CI, devops.md:1097) — the dev
# database and the nginx stack are never touched. `E2ETestSeeder` still
# truncates + reseeds on every run (via global-setup), but only the e2e DB.
# The serve process runs as uid 1000 (B171): e2e logins log info events to
# the SHARED storage/logs — a root-owned daily file 500s every php-fpm
# (uid 1000) request that logs, including dev logins on localhost:8888.
# The e2e serve log is removed first — a root-owned leftover from
# pre-B171 runs makes the uid-1000 redirect fail with permission denied
# and the server silently never starts.
# `--no-reload` is REQUIRED: ServeCommand otherwise passes only a curated
# env whitelist (APP_ENV/PATH/...) to the `php -S` child, silently dropping
# the DB_DATABASE/REDIS_DB overrides (the server would hit the dev DB).
test-e2e: ## Run e2e tests against the dedicated e2e database (dev DB untouched)
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
	$(DC) exec -u 1000 -d dev sh -c 'env DB_DATABASE=portfolio_e2e REDIS_DB=1 php artisan serve --no-reload --host=0.0.0.0 --port=8001 >/tmp/e2e-serve.log 2>&1'; \
	trap '$(DE) sh -c "pkill -f \"[p]hp artisan serve.*8001\"; pkill -f \"[p]hp -S .*8001\"; true"' EXIT; \
	$(DE) sh -c 'npx wait-on -t 30000 http://127.0.0.1:8001/up || (echo "e2e server failed:"; cat /tmp/e2e-serve.log; exit 1)'; \
	$(DE) env DB_DATABASE=portfolio_e2e REDIS_DB=1 PLAYWRIGHT_BASE_URL=http://127.0.0.1:8001 npx playwright test

coverage: ## Run all test suites with coverage (80% line coverage threshold, per testing spec)
	$(DE) env XDEBUG_MODE=coverage php artisan test --coverage --min=80
	$(DE) npx vitest run --coverage

coverage-backend: ## Run backend tests with coverage (80% line coverage threshold)
	$(DE) env XDEBUG_MODE=coverage php artisan test --coverage --min=80

coverage-frontend: ## Run frontend tests with coverage (80% line coverage threshold)
	$(DE) npx vitest run --coverage

lint: ## Lint all code (mirrors the CI lint job: pint, biome, eslint, stylelint, spectral, commitlint)
	$(DE) ./vendor/bin/pint --test
	$(DE) npx biome ci resources/js
	$(DE) npx eslint resources/js
	$(DE) npx stylelint "resources/js/**/*.vue" || true
	$(DE) npx @stoplight/spectral-cli lint docs/openapi/openapi.yaml --ruleset .spectral.yaml
	$(DE) npx @stoplight/spectral-cli lint docs/openapi/inertia/inertia.yaml --ruleset .spectral.yaml
	@MAIN="$$(git rev-parse --verify -q main 2>/dev/null)"; \
	if [ -n "$$MAIN" ] && [ "$$MAIN" != "$$(git rev-parse HEAD)" ]; then \
		$(DE) npx commitlint --from main --to HEAD; \
	fi

lint\:css: ## Validate CSS in Vue <style> blocks (non-blocking)
	$(DE) npx stylelint "resources/js/**/*.vue" || true

spectral: ## Lint both OpenAPI specs with the project ruleset (.spectral.yaml)
	$(DE) npx @stoplight/spectral-cli lint docs/openapi/openapi.yaml --ruleset .spectral.yaml
	$(DE) npx @stoplight/spectral-cli lint docs/openapi/inertia/inertia.yaml --ruleset .spectral.yaml

commitlint: ## Lint commit messages (conventional commits; current branch vs main)
	@MAIN="$$(git rev-parse --verify -q main 2>/dev/null)"; \
	if [ -n "$$MAIN" ] && [ "$$MAIN" != "$$(git rev-parse HEAD)" ]; then \
		$(DE) npx commitlint --from main --to HEAD; \
	fi

format: ## Auto-format code
	$(DE) ./vendor/bin/pint
	$(DE) npx biome check --write --unsafe resources/js

typecheck: ## TypeScript type check (includes API + Inertia type generation)
	$(DE) npm run typecheck

secrets-init: ## Generate production secrets and encrypt .env.prod.local (see secrets-management.md)
	$(DE) ./devops/secrets/init.sh

deploy: ## Deploy to production (usage: make deploy GIT_REF=<tag-or-commit>)
	@[ -n "$(GIT_REF)" ] || (echo "ERROR: GIT_REF is required. Usage: make deploy GIT_REF=<tag-or-commit>" && exit 1)
	ansible-playbook -i devops/deploy/hosts.yml devops/deploy/deploy.yml -e "git_ref=$(GIT_REF)"
	# On first deploy the playbook reads AGE_PRIVATE_KEY from the environment to provision /opt/portfolio/.env.prod (see secrets-management.md)

rollback: ## Rollback deployed services (usage: make rollback [SERVICE=<name>])
	ansible-playbook -i devops/deploy/hosts.yml devops/deploy/rollback.yml -e "service=$(or $(SERVICE),all)"
