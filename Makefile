.PHONY: up down up-localhost down-localhost restart build install migrate seed setup setup-localhost test test-unit test-feature test-frontend lint lint\:css format typecheck health secrets-init deploy rollback

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

health: ## Show service health status (all services must be healthy before proceeding)
	@echo "Service health status:"
	@$(DC) ps -a --format 'table {{.Name}}\t{{.Status}}'

install: ## Install PHP + JS dependencies
	$(DE) composer install
	$(DE) npm ci

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

test: ## Run all test suites
	$(DE) php artisan test
	$(DE) npx vitest run
	$(DE) npx playwright test

test-unit: ## Run backend unit tests only
	$(DE) php artisan test tests/unit/backend/

test-feature: ## Run backend feature tests only
	$(DE) php artisan test tests/integration/backend/

test-frontend: ## Run frontend tests only
	$(DE) npx vitest run

lint: ## Lint all code
	$(DE) ./vendor/bin/pint --test
	$(DE) npx biome ci resources/js
	$(DE) npx eslint resources/js

lint\:css: ## Validate CSS in Vue <style> blocks (non-blocking)
	$(DE) npx stylelint "resources/js/**/*.vue" || true

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
