.PHONY: up down build restart logs shell migrate lint fix test

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

restart: down up

logs:
	docker compose logs -f

shell:
	docker compose exec app sh

migrate:
	docker compose exec app php artisan migrate

lint:
	docker compose exec app sh -c "cd /var/www && vendor/bin/pint --test"
	docker compose exec app sh -c "cd /var/www && vendor/bin/phpstan analyse --no-progress"
	docker compose exec node sh -c "cd /app && npx eslint src/"
	docker compose exec node sh -c "cd /app && npx prettier --check src/"

fix:
	docker compose exec app sh -c "cd /var/www && vendor/bin/pint"
	docker compose exec node sh -c "cd /app && npx prettier --write src/"

test:
	docker compose exec app sh -c "cd /var/www && vendor/bin/phpunit"
