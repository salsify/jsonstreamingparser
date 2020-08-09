PHPSTAN_LEVEL=5
PHPSTAN_VERSION="0.12.25"

tools/php-cs-fixer:
	wget --directory-prefix=tools --quiet https://cs.sensiolabs.org/download/php-cs-fixer-v2.phar
	mv tools/php-cs-fixer-v2.phar tools/php-cs-fixer
	chmod +x tools/php-cs-fixer

tools/phpstan:
	wget --directory-prefix=tools --quiet https://github.com/phpstan/phpstan/releases/download/$(PHPSTAN_VERSION)/phpstan.phar
	mv tools/phpstan.phar tools/phpstan
	chmod +x tools/phpstan

tools/phpunit:
	wget --directory-prefix=tools --quiet https://phar.phpunit.de/phpunit-7.phar
	mv tools/phpunit-7.phar tools/phpunit
	chmod +x tools/phpunit

tools/phpunit-9:
	wget --directory-prefix=tools --quiet https://phar.phpunit.de/phpunit-9.phar
	mv tools/phpunit-9.phar tools/phpunit
	chmod +x tools/phpunit

phpcs: tools/php-cs-fixer
	rm -f .php_cs.cache
	composer install --optimize-autoloader --no-dev --no-suggest --quiet
	tools/php-cs-fixer fix --dry-run --stop-on-violation -v

phpstan: tools/phpstan
	tools/phpstan analyze --level=$(PHPSTAN_LEVEL) --no-progress src/

test: tools/phpunit
	composer install --optimize-autoloader --no-suggest --quiet
	tools/phpunit

test-php-8: tools/phpunit-9
	composer install --optimize-autoloader --no-suggest --quiet
	tools/phpunit

test-coverage: tools/phpunit
	composer install --optimize-autoloader --no-suggest --quiet
	tools/phpunit --coverage-clover build/logs/clover.xml

fix-cs: tools/php-cs-fixer
	tools/php-cs-fixer fix -v

clean:
	rm tools/ vendor/ -fr

.PHONY: clean phpcs fix-cs
