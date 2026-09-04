.PHONY: up down restart build logs bash test composer-install es-health es-indices db-status

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

build:
	docker compose build

logs:
	docker compose logs -f

bash:
	docker compose exec app bash

test:
	docker compose exec app vendor/bin/phpunit

composer-install:
	docker compose exec app composer install

es-health:
	curl -s http://localhost:9200/_cluster/health?pretty

es-indices:
	curl -s http://localhost:9200/_cat/indices?v

db-status:
	docker compose exec postgres pg_isready -U przelewy_user -d przelewy_db
