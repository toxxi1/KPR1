# See:
# http://www.gnu.org/software/make/manual/make.html

include docker/.env

EXEC_PHP      = docker compose exec app
EXEC_MYSQL    = docker compose exec postgres
EXEC_REDIS    = docker compose exec redis
ARTISAN       = $(EXEC_PHP) php artisan
COMPOSER      = $(EXEC_PHP) composer
DOCKER_F      = $(COMPOSE_FILE)


# This allows us to accept extra arguments
%:
	@:
args = `arg="$(filter-out $@,$(MAKECMDGOALS))" && echo $${arg:-${1}}`

### project setup
rebuild:
	cd ./docker \
	&& docker compose -f $(DOCKER_F) down --remove-orphans \
	&& docker compose -f $(DOCKER_F) build --parallel \
	&& docker compose -f $(DOCKER_F) up -d \

### project setup
rebuild-debug:
	cd ./docker \
	&& docker compose -f $(DOCKER_F) down --remove-orphans \
	&& docker compose -f $(DOCKER_F) -f docker-compose-debug.yml build --parallel \
	&& docker compose -f $(DOCKER_F) -f docker-compose-debug.yml up -d \

down:
	cd ./docker \
	&& docker compose down

up:
	cd ./docker \
	&& docker compose -f $(DOCKER_F) up -d \

php-shell:
	cd ./docker \
	&& $(EXEC_PHP) bash

redis-cli:
	cd ./docker \
	&& $(EXEC_REDIS) sh

optimize:
	cd ./docker \
	&& $(ARTISAN) optimize

tinker:
	cd ./docker \
	&& $(ARTISAN) tinker

artisan:
	cd ./docker \
	&& $(ARTISAN) $(call args)

migrate:
	cd ./docker \
	&& $(ARTISAN) migrate

migrate-rollback:
	cd ./docker \
	&& $(ARTISAN) migrate:rollback

composer-install:
	cd ./docker \
	&& $(COMPOSER) install

# Refresh composer.lock hash
composer-update-hash:
	cd ./docker \
	&& $(COMPOSER) update --lock

composer-dump-autoload:
	cd ./docker \
	&& $(COMPOSER) dump-autoload

create-user:
	# todo

watch:
	cd ./docker \
	&& docker compose run --rm nodejs npm run watch

unittest:
	cd ./docker \
	&& $(EXEC_PHP) vendor/bin/paratest

tail-info:
	cd ./docker \
	&& $(EXEC_PHP) tail -f storage/logs/laravel-info-$(shell date +%Y-%m-%d).log -n 500

tail-error:
	cd ./docker \
	&& $(EXEC_PHP) tail -f storage/logs/laravel-error-$(shell date +%Y-%m-%d).log -n 500

flush-logs:
	rm -rf ./docker/data/storage/logs/*.log
