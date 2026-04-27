UID=$(shell id -u)
GID=$(shell id -g)
DOCKER_RUN=UID=$(UID) GID=$(GID) docker compose run --rm php

bin=vendor/bin
chrome:=$(shell command -v google-chrome 2>/dev/null)
codeSnifferRuleset=codesniffer-ruleset.xml
coverage=$(temp)/coverage
coverageClover=$(coverage)/coverage.xml
php=php
src=src
temp=temp
tests=tests
dirs:=$(src) $(tests)

all:
	 @$(MAKE) -pRrq -f $(lastword $(MAKEFILE_LIST)) : 2>/dev/null | awk -v RS= -F: '/^# File/,/^# Finished Make data base/ {if ($$1 !~ "^[#.]") {print $$1}}' | sort | egrep -v -e '^[^[:alnum:]]' -e '^$@$$'

# Setup

docker-build:
	UID=$(UID) GID=$(GID) docker compose build

composer:
	$(DOCKER_RUN) composer install

composer-update:
	$(DOCKER_RUN) composer update

reset:
	rm -rf $(temp)/cache
	$(DOCKER_RUN) composer dumpautoload

di: reset
	$(DOCKER_RUN) bin/extract-services

fix: reset check-syntax phpcbf phpcs phpstan test

# QA

check-syntax:
	$(DOCKER_RUN) $(bin)/parallel-lint -e $(php) $(dirs)

phpcs:
	$(DOCKER_RUN) $(bin)/phpcs -sp --standard=$(codeSnifferRuleset) --extensions=php $(dirs)

phpcbf:
	$(DOCKER_RUN) $(bin)/phpcbf -spn --standard=$(codeSnifferRuleset) --extensions=php $(dirs) ; true

phpstan:
	$(DOCKER_RUN) $(bin)/phpstan analyze $(dirs) --level max

# Tests

test:
	$(DOCKER_RUN) $(bin)/phpunit

test-coverage: reset
	$(DOCKER_RUN) $(bin)/phpunit --coverage-html=$(coverage)

test-coverage-clover: reset
	$(DOCKER_RUN) $(bin)/phpunit --coverage-clover=$(coverageClover)

test-coverage-report: test-coverage-clover
	$(DOCKER_RUN) $(bin)/php-coveralls --coverage_clover=$(coverageClover) --verbose

test-coverage-open: test-coverage
ifndef chrome
	open -a 'Google Chrome' $(coverage)/index.html
else
	google-chrome $(coverage)/index.html
endif

ci: check-syntax phpcs phpstan test-coverage-report
