.PHONY: build up down logs shell migrate seed fresh artisan test

# Docker compose shortcuts
build:
	docker compose -f docker/docker-compose.yml build

up:
	docker compose -f docker/docker-compose.yml up -d

down:
	docker compose -f docker/docker-compose.yml down

logs:
	docker compose -f docker/docker-compose.yml logs -f

shell:
	docker compose -f docker/docker-compose.yml exec app /bin/sh

# Laravel shortcuts inside the container
migrate:
	docker compose -f docker/docker-compose.yml exec app php artisan migrate

seed:
	docker compose -f docker/docker-compose.yml exec app php artisan db:seed

fresh:
	docker compose -f docker/docker-compose.yml exec app php artisan migrate:fresh --seed

artisan:
	docker compose -f docker/docker-compose.yml exec app php artisan $(CMD)

test:
	docker compose -f docker/docker-compose.yml exec app php artisan test
