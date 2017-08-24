ci: covers phpunit cs stan

test: covers phpunit

covers:
	docker-compose run --rm app ./vendor/bin/covers-validator

phpunit:
	docker-compose run --rm app ./vendor/bin/phpunit

cs:
	docker-compose run --rm app ./vendor/bin/phpcs

stan:
	docker-compose run --rm app ./vendor/bin/phpstan analyse --level=1 --no-progress cli/ contexts/ src/ tests/

.PHONY: ci test covers phpunit cs stan
