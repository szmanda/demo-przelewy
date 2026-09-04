.PHONY: up down restart build logs bash test test-unit test-api codecept messenger-consume composer-install es-health es-indices db-status

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

test: test-unit test-api

test-unit:
	vendor/bin/phpunit

test-api:
	vendor/bin/codecept run

codecept:
	vendor/bin/codecept run

messenger-consume:
	docker compose exec app php bin/console messenger:consume async -vv

composer-install:
	docker compose exec app composer install

es-health:
	curl -s http://localhost:9200/_cluster/health?pretty

es-indices:
	curl -s http://localhost:9200/_cat/indices?v

db-status:
	docker compose exec postgres pg_isready -U przelewy_user -d przelewy_db
