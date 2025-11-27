SHELL:=/bin/bash

FILENAME=komtetdelivery.tar.gz

help:
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[0;36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST) | sort

build:  ## Собрать контейнер
	@docker-compose build

stop: ## Остановить все контейнеры
	@docker-compose down

start_web7: stop  ## Запустить контейнер на php 7.4
	@docker-compose up web7

start_web8: stop  ## Запустить контейнер на php 8
	@docker-compose up web8

update:  ## Установить/Обновить модуль
	@rm -rf php/wa-apps/shop/plugins/komtetdelivery &&\
	 echo "Старая папка удалена"
	@cp -r komtetdelivery php/wa-apps/shop/plugins &&\
	 echo "Новая папка скопирована"

release:  ## Архивировать для загрузки в маркет
	@mkdir -p dist
	@rm -f dist/$(FILENAME) || true
	@tar \
	 --exclude='komtetdelivery/lib/vendors/komtet-kassa-php-sdk/docker_env' \
	 --exclude='komtetdelivery/lib/vendors/komtet-kassa-php-sdk/examples' \
	 --exclude='komtetdelivery/lib/vendors/komtet-kassa-php-sdk/tests' \
	 --exclude='komtetdelivery/lib/vendors/komtet-kassa-php-sdk/.git' \
	 --exclude='komtetdelivery/lib/vendors/komtet-kassa-php-sdk/.gitattributes' \
	 --exclude='komtetdelivery/lib/vendors/komtet-kassa-php-sdk/.gitignore' \
	 --exclude='komtetdelivery/lib/vendors/komtet-kassa-php-sdk/README.md' \
	 --exclude='komtetdelivery/lib/vendors/komtet-kassa-php-sdk/RELEASE.md' \
	 --exclude='komtetdelivery/lib/vendors/komtet-kassa-php-sdk/phpunit.xml' \
	 -czvf dist/$(FILENAME) komtetdelivery/

.PHONY: help release
.DEFAULT_GOAL := help
