SHELL:=/bin/bash

help:
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[0;36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST) | sort

build:  ## Собрать контейнер
	@docker-compose build

stop: ## Остановить все контейнеры
	@docker-compose down

start_web5: stop  ## Запустить контейнер
	@docker-compose up -d web5

start_web7: stop  ## Запустить контейнер
	@docker-compose up -d web7

update:  ## Установить/Обновить модуль
	@rm -rf php/wa-apps/shop/plugins/komtetdelivery &&\
	 cp -r komtetdelivery php/wa-apps/shop/plugins

release:  ## Архивировать для загрузки в маркет
	@tar\
	 --exclude='./komtetdelivery/lib/vendors/komtet-kassa-php-sdk/.*'\
	 --exclude='./komtetdelivery/lib/vendors/komtet-kassa-php-sdk/docker_env'\
	 --exclude='./komtetdelivery/lib/vendors/komtet-kassa-php-sdk/tests'\
	 -czvf komtetdelivery.tar.gz komtetdelivery/

.PHONY: help
.DEFAULT_GOAL := help
