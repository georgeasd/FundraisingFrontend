ci: covers phpunit cs stan phpunit-system

test: covers phpunit

setup:
	docker-compose run --rm app ./vendor/bin/doctrine orm:schema-tool:create

covers:
	docker-compose run --rm app ./vendor/bin/covers-validator

phpunit:
	docker-compose run --rm app ./vendor/bin/phpunit

phpunit-system:
	docker-compose run --rm app ./vendor/bin/phpunit tests/System/

cs:
	docker-compose run --rm app ./vendor/bin/phpcs

stan:
	docker-compose run --rm app php -d memory_limit=-1 vendor/bin/phpstan analyse --level=1 --no-progress cli/ contexts/ src/ tests/

.PHONY: setup ci test covers phpunit phpunit-system cs stan
