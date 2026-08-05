DOCKER_COMPOSE ?= docker compose
PHP := $(DOCKER_COMPOSE) run --rm php

.PHONY: build shell install update validate test analyse check analyze

build:
	$(DOCKER_COMPOSE) build php

shell:
	$(PHP) bash

install:
	$(PHP) composer install

update:
	$(PHP) composer update

validate:
	$(PHP) composer validate --strict
	$(PHP) composer validate --strict packages/core/composer.json
	$(PHP) composer validate --strict packages/cli/composer.json
	$(PHP) composer validate --strict packages/laravel/composer.json

test:
	$(PHP) vendor/bin/phpunit

analyse:
	$(PHP) vendor/bin/phpstan analyse

check: validate test analyse

analyze:
	$(PHP) packages/cli/bin/upgrade-intel analyze $(ARGS)
