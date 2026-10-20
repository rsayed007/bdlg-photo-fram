DC  = docker compose
APP = $(DC) exec app

.PHONY: up down build shell migrate fresh test test-browser pint stan lint logs ps

up:            ## Build (if needed) and start all services
	@test -f .env || cp .env.example .env
	$(DC) up -d --build
	$(DC) ps

down:          ## Stop all services
	$(DC) down

build:         ## Rebuild images
	$(DC) build

shell:         ## Shell inside the app container
	$(APP) bash

migrate:       ## Run migrations
	$(APP) php artisan migrate

fresh:         ## Drop all tables, migrate and seed (admin + demo data + sample frame)
	$(APP) php artisan migrate:fresh --seed --force

test:          ## Run the full Pest suite (uses the bdlg_testing database)
	$(APP) php artisan config:clear
	$(APP) ./vendor/bin/pest --exclude-group=browser
	$(APP) ./vendor/bin/pest --group=browser

pint:          ## Format PHP with Laravel Pint
	$(APP) ./vendor/bin/pint

stan:          ## Static analysis with Larastan
	$(APP) ./vendor/bin/phpstan analyse --memory-limit=1G

lint:          ## ESLint + Prettier check
	$(DC) exec vite npm run lint:check
	$(DC) exec vite npm run format:check

logs:          ## Tail logs from all services
	$(DC) logs -f --tail=100

ps:
	$(DC) ps
