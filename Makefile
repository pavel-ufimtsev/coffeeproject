# CoffeeProject - Docker shortcuts
DC   := docker compose
EXEC := $(DC) exec app

.DEFAULT_GOAL := help
.PHONY: help init setup up down build restart logs ps shell artisan composer npm migrate fresh seed-admin test mysql crm-check

help: ## Show available commands
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

init: ## Create .env from .env.example with your host UID/GID
	@test -f .env || { cp .env.example .env && \
		sed -i.bak "s/^HOST_UID=.*/HOST_UID=$$(id -u)/; s/^HOST_GID=.*/HOST_GID=$$(id -g)/" .env && rm -f .env.bak && \
		echo ".env created"; }
	@test -f src/.env || { test -f src/.env.example && cp src/.env.example src/.env && echo "src/.env created"; } || true

setup: init build ## First-time setup: .env files, images, dependencies, app key, migrations
	$(DC) run --rm --no-deps app composer install
	@grep -q '^APP_KEY=$$' src/.env && $(DC) run --rm --no-deps app php artisan key:generate || true
	$(DC) up -d
	$(EXEC) php artisan migrate --force
	$(MAKE) seed-admin

up: ## Start all containers
	$(DC) up -d

down: ## Stop and remove containers
	$(DC) down

build: ## Build the Docker images (php + node)
	$(DC) build

restart: ## Restart all containers (needed after editing src/.env for queue/scheduler)
	$(DC) restart

logs: ## Tail logs (make logs s=queue for one service)
	$(DC) logs -f --tail=100 $(s)

ps: ## Show container status
	$(DC) ps

shell: ## Bash shell in the app container
	$(EXEC) bash

artisan: ## Run artisan: make artisan cmd="migrate"
	$(EXEC) php artisan $(cmd)

composer: ## Run composer: make composer cmd="require foo/bar"
	$(EXEC) composer $(cmd)

npm: ## Run npm in the node container: make npm cmd="install"
	$(DC) exec node npm $(cmd)

migrate: ## Run database migrations
	$(EXEC) php artisan migrate

fresh: ## Drop all tables, re-migrate and seed
	$(EXEC) php artisan migrate:fresh --seed

seed-admin: ## Create the admin account from ADMIN_* in src/.env
	$(EXEC) php artisan db:seed --class=AdminUserSeeder --force

test: ## Run the test suite
	$(EXEC) php artisan test

mysql: ## MySQL CLI as the app user
	$(DC) exec mysql sh -c 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

crm-check: ## Check the retailCRM connection and list store, delivery and payment codes
	$(EXEC) php artisan retailcrm:check
