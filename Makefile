# ═══════════════════════════════════════════════════════════════
# Fleet Management System — Developer Commands
# ═══════════════════════════════════════════════════════════════

.DEFAULT_GOAL := help
SHELL := /bin/bash

# Two Docker setups are available:
#   1. Root docker-compose.yml (Nginx + PHP-FPM, production-like)
#   2. infra/docker/compose.yml (artisan serve, simpler dev setup)
# Set COMPOSE_MODE=infra to use the infra setup.
ifeq ($(COMPOSE_MODE),infra)
  DC := docker compose -f infra/docker/compose.yml
  APP := $(DC) exec backend
else
  DC := docker compose
  APP := $(DC) exec app
endif
ARTISAN := $(APP) php artisan
NPM := $(DC) exec frontend npm

# ── Lifecycle ────────────────────────────────────────────────
.PHONY: up down restart build rebuild

up: ## Start all services
	$(DC) up -d

down: ## Stop all services
	$(DC) down

restart: down up ## Restart all services

build: ## Build Docker images
	$(DC) build

rebuild: ## Rebuild images from scratch (no cache)
	$(DC) build --no-cache

# ── Installation ─────────────────────────────────────────────
.PHONY: install install-backend install-frontend

install: install-backend install-frontend ## Install all dependencies

install-backend: ## Install backend dependencies
	$(APP) composer install

install-frontend: ## Install frontend dependencies
	$(NPM) install

# ── Database ─────────────────────────────────────────────────
.PHONY: migrate seed fresh rollback

migrate: ## Run database migrations
	$(ARTISAN) migrate

seed: ## Run database seeders
	$(ARTISAN) db:seed

fresh: ## Fresh migration + seed (destroys all data)
	$(ARTISAN) migrate:fresh --seed

rollback: ## Rollback last migration batch
	$(ARTISAN) migrate:rollback

# ── Testing ──────────────────────────────────────────────────
.PHONY: test test-backend test-frontend lint

test: test-backend test-frontend ## Run all tests

test-backend: ## Run backend tests (Pest/PHPUnit)
	$(APP) php artisan test

test-frontend: ## Run frontend unit tests (Vitest)
	$(NPM) run test:unit:run

lint: ## Run linters
	$(APP) ./vendor/bin/pint --test
	$(NPM) run lint

# ── Host-native commands (no Docker) ─────────────────────────
.PHONY: test-host build-frontend-host

test-host: ## Run backend tests on host (requires PHP + DB)
	cd backend && php artisan test

build-frontend-host: ## Build frontend on host (requires Node)
	cd frontend && npm run build

typecheck-host: ## TypeScript check on host
	cd frontend && npx vue-tsc --noEmit

# ── Development ──────────────────────────────────────────────
.PHONY: shell tinker logs queue-restart cache-clear

shell: ## Open shell in PHP container
	$(APP) bash || $(APP) sh

tinker: ## Open Laravel Tinker REPL
	$(ARTISAN) tinker

logs: ## Tail all container logs
	$(DC) logs -f

logs-app: ## Tail only app container logs
	$(DC) logs -f app backend 2>/dev/null

queue-restart: ## Restart queue workers
	$(DC) restart queue

cache-clear: ## Clear all Laravel caches
	$(ARTISAN) optimize:clear

# ── Code Quality ─────────────────────────────────────────────
.PHONY: format pint

format: pint ## Format code

pint: ## Run Laravel Pint code formatter
	$(APP) ./vendor/bin/pint

# ── Production ───────────────────────────────────────────────
.PHONY: optimize

optimize: ## Cache config, routes, views for production
	$(ARTISAN) optimize

# ── Utilities ────────────────────────────────────────────────
.PHONY: routes api-docs

routes: ## List all registered routes
	$(ARTISAN) route:list

api-docs: ## Generate/view API documentation
	@echo "API docs: http://localhost:8080/docs/api (root setup) or http://localhost:8000/docs/api (infra setup)"

# ── Help ─────────────────────────────────────────────────────
.PHONY: help

help: ## Show this help
	@echo "Fleet Management System — Available commands:"
	@echo ""
	@echo "  Set COMPOSE_MODE=infra to use infra/docker/compose.yml instead of root docker-compose.yml"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-24s\033[0m %s\n", $$1, $$2}'
	@echo ""
