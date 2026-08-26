# MetaCreator.Dev — developer task runner
# Run `make` or `make help` for the full list.

SHELL       := /bin/bash
.DEFAULT_GOAL := help

COMPOSE     := docker compose
API         := $(COMPOSE) exec -T api
API_TTY     := $(COMPOSE) exec api
WEB         := $(COMPOSE) exec -T web
WEB_TTY     := $(COMPOSE) exec web
ENV         ?= staging
REF         ?= main
SERVICE     ?=

# Colours
C_OK   := \033[0;32m
C_INFO := \033[0;36m
C_WARN := \033[0;33m
C_OFF  := \033[0m

.PHONY: help
help: ## Show this help
	@echo ""
	@echo -e "$(C_INFO)MetaCreator.Dev$(C_OFF) — available targets:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
	  | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(C_OK)%-18s$(C_OFF) %s\n", $$1, $$2}'
	@echo ""

# ── Lifecycle ─────────────────────────────────────────────────────────────────

.PHONY: setup
setup: ## First-time setup: env files, build, install, migrate, seed
	@echo -e "$(C_INFO)▸ Preparing environment files$(C_OFF)"
	@[ -f .env ] || cp .env.example .env
	@[ -f apps/api/.env ] || cp apps/api/.env.example apps/api/.env
	@[ -f apps/web/.env.local ] || cp apps/web/.env.example apps/web/.env.local
	@echo -e "$(C_INFO)▸ Building images$(C_OFF)"
	@$(COMPOSE) build
	@echo -e "$(C_INFO)▸ Starting data services$(C_OFF)"
	@$(COMPOSE) up -d mysql redis minio mailpit
	@$(MAKE) --no-print-directory wait-db
	@$(COMPOSE) up -d
	@echo -e "$(C_INFO)▸ Installing dependencies$(C_OFF)"
	@$(API) composer install
	@$(WEB) npm ci
	@echo -e "$(C_INFO)▸ Preparing the application$(C_OFF)"
	@$(API) php artisan key:generate --force
	@$(API) php artisan storage:link || true
	@$(API) php artisan migrate --force --seed
	@$(MAKE) --no-print-directory urls

.PHONY: up
up: ## Start the stack
	@$(COMPOSE) up -d
	@$(MAKE) --no-print-directory urls

.PHONY: down
down: ## Stop the stack
	@$(COMPOSE) down

.PHONY: restart
restart: down up ## Restart the stack

.PHONY: destroy
destroy: ## Stop and DELETE all volumes (irreversible: wipes the local database)
	@echo -e "$(C_WARN)This deletes the local database, Redis and MinIO data.$(C_OFF)"
	@read -p "Type 'destroy' to confirm: " ans; [ "$$ans" = "destroy" ] || exit 1
	@$(COMPOSE) down -v

.PHONY: build
build: ## Rebuild images
	@$(COMPOSE) build --pull

.PHONY: ps
ps: ## Show container status
	@$(COMPOSE) ps

.PHONY: logs
logs: ## Tail logs (make logs SERVICE=api)
	@$(COMPOSE) logs -f --tail=100 $(SERVICE)

.PHONY: urls
urls:
	@echo ""
	@echo -e "  $(C_OK)Web$(C_OFF)       http://localhost:3000"
	@echo -e "  $(C_OK)API$(C_OFF)       http://localhost:8080"
	@echo -e "  $(C_OK)Horizon$(C_OFF)   http://localhost:8080/horizon"
	@echo -e "  $(C_OK)Mailpit$(C_OFF)   http://localhost:8025"
	@echo -e "  $(C_OK)MinIO$(C_OFF)     http://localhost:9001  (minio / minio123)"
	@echo ""

wait-db:
	@echo -n "▸ Waiting for MySQL "
	@for i in $$(seq 1 60); do \
	  if $(COMPOSE) exec -T mysql mysqladmin ping -h localhost --silent >/dev/null 2>&1; then \
	    echo -e " $(C_OK)ready$(C_OFF)"; exit 0; fi; \
	  echo -n "."; sleep 2; \
	done; echo -e " $(C_WARN)timed out$(C_OFF)"; exit 1

# ── Shells & commands ─────────────────────────────────────────────────────────

.PHONY: sh-api sh-web sh-db sh-redis
sh-api: ## Shell into the API container
	@$(API_TTY) sh
sh-web: ## Shell into the web container
	@$(WEB_TTY) sh
sh-db: ## MySQL shell
	@$(COMPOSE) exec mysql mysql -umetacreator -psecret metacreator
sh-redis: ## Redis CLI
	@$(COMPOSE) exec redis redis-cli

.PHONY: artisan
artisan: ## Run an artisan command: make artisan CMD="route:list"
	@$(API_TTY) php artisan $(CMD)

.PHONY: composer
composer: ## Run composer: make composer CMD="require foo/bar"
	@$(API_TTY) composer $(CMD)

.PHONY: npm
npm: ## Run npm in the web container: make npm CMD="install foo"
	@$(WEB_TTY) npm $(CMD)

# ── Database ──────────────────────────────────────────────────────────────────

.PHONY: migrate db-rollback fresh seed
migrate: ## Run pending migrations
	@$(API) php artisan migrate
db-rollback: ## Roll back the last migration batch
	@$(API) php artisan migrate:rollback
fresh: ## Drop everything, migrate and seed
	@$(API) php artisan migrate:fresh --seed
seed: ## Run seeders
	@$(API) php artisan db:seed

# ── Queues ────────────────────────────────────────────────────────────────────

.PHONY: queue-restart queue-failed queue-retry
queue-restart: ## Reload queue workers (needed after PHP changes)
	@$(API) php artisan horizon:terminate
	@$(COMPOSE) restart worker
queue-failed: ## List failed jobs
	@$(API) php artisan queue:failed
queue-retry: ## Retry all failed jobs
	@$(API) php artisan queue:retry all

# ── Quality ───────────────────────────────────────────────────────────────────

.PHONY: test test-api test-web test-e2e
test: test-api test-web ## Run unit + integration suites
test-api: ## Backend tests (Pest)
	@$(API) php artisan test --parallel
test-web: ## Frontend tests (Vitest)
	@$(WEB) npm run test -- --run
test-e2e: ## End-to-end tests (Playwright)
	@$(WEB) npm run test:e2e

.PHONY: lint format analyse
lint: ## Lint everything
	@$(API) ./vendor/bin/pint --test
	@$(API) ./vendor/bin/phpstan analyse --memory-limit=1G
	@$(WEB) npm run lint
	@$(WEB) npx tsc --noEmit
format: ## Auto-format everything
	@$(API) ./vendor/bin/pint
	@$(WEB) npm run format
analyse: ## Static analysis only
	@$(API) ./vendor/bin/phpstan analyse --memory-limit=1G

.PHONY: audit
audit: ## Dependency vulnerability audit
	@$(API) composer audit
	@$(WEB) npm audit --audit-level=high

# ── Utilities ─────────────────────────────────────────────────────────────────

.PHONY: cache-clear ide-helper openapi
cache-clear: ## Clear all application caches
	@$(API) php artisan optimize:clear
ide-helper: ## Regenerate IDE metadata
	@$(API) php artisan ide-helper:generate
	@$(API) php artisan ide-helper:models -N
openapi: ## Regenerate the OpenAPI spec and the typed frontend client
	@$(API) php artisan api:spec
	@$(WEB) npm run generate:client

# ── Deployment ────────────────────────────────────────────────────────────────

.PHONY: deploy rollback provision
deploy: ## Deploy: make deploy ENV=production REF=v1.0.0
	@cd deploy/ansible && ansible-playbook -i inventories/$(ENV)/hosts.yml deploy.yml \
	  --extra-vars "git_ref=$(REF)" --ask-vault-pass
rollback: ## Roll back: make rollback ENV=production [TO=<release>]
	@cd deploy/ansible && ansible-playbook -i inventories/$(ENV)/hosts.yml rollback.yml \
	  $(if $(TO),--extra-vars "rollback_to=$(TO)",) --ask-vault-pass
provision: ## First-time host provisioning (rarely needed)
	@cd deploy/ansible && ansible-playbook -i inventories/$(ENV)/hosts.yml provision.yml --ask-vault-pass
