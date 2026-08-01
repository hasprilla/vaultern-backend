.PHONY: build up down logs shell migrate seed fresh artisan test redis-cli up-realtime docs-docker

COMPOSE=docker compose -f docker/docker-compose.yml --env-file .env.docker

build:
	$(COMPOSE) build

up:
	@test -f .env.docker || cp docker/.env.docker.example .env.docker
	$(COMPOSE) up --build -d
	@echo ""
	@echo "API:   http://127.0.0.1:8000/api/v1/health"
	@echo "Queue: redis  |  Scheduler: schedule:work"
	@echo "Seed:  make seed"

up-realtime:
	@test -f .env.docker || cp docker/.env.docker.example .env.docker
	BROADCAST_CONNECTION=reverb $(COMPOSE) --profile realtime up --build -d
	@echo "Reverb ws://127.0.0.1:8080"

down:
	$(COMPOSE) down

logs:
	$(COMPOSE) logs -f

shell:
	$(COMPOSE) exec app /bin/sh

migrate:
	$(COMPOSE) exec app php artisan migrate --force

seed:
	$(COMPOSE) exec app php artisan db:seed --class=DemoHhUserSeeder --force

fresh:
	$(COMPOSE) exec app php artisan migrate:fresh --seed --force

artisan:
	$(COMPOSE) exec app php artisan $(CMD)

test:
	$(COMPOSE) exec app php artisan test

redis-cli:
	$(COMPOSE) exec redis redis-cli ping
