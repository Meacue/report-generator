.PHONY: up down build restart logs shell migrate lint lint-docker fix test

HADOLINT_IMAGE := ghcr.io/hadolint/hadolint:v2.14.0@sha256:27086352fd5e1907ea2b934eb1023f217c5ae087992eb59fde121dce9c9ff21e

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

lint: lint-docker
	docker compose exec app sh -c "cd /var/www && vendor/bin/pint --test"
	docker compose exec app sh -c "cd /var/www && vendor/bin/phpstan analyse --no-progress"
	docker compose exec node sh -c "cd /app && npx eslint src/"
	docker compose exec node sh -c "cd /app && npx prettier --check src/"

# hadolint runs via `docker run` (no compose containers needed).
# MSYS_NO_PATHCONV=1 stops Git Bash on Windows from mangling the container paths;
# it is a harmless no-op on Linux/CI.
lint-docker:
	MSYS_NO_PATHCONV=1 docker run --rm -v "$$(pwd):/repo:ro" -w /repo $(HADOLINT_IMAGE) \
		hadolint .docker/app/Dockerfile .docker/worker/Dockerfile .docker/node/Dockerfile

fix:
	docker compose exec app sh -c "cd /var/www && vendor/bin/pint"
	docker compose exec node sh -c "cd /app && npx prettier --write src/"

test:
	docker compose exec app sh -c "cd /var/www && vendor/bin/phpunit"
