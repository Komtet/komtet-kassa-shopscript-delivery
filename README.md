# komtet_kassa_shopscript_delivery

## Запуск проекта

* Склонируйте репозиторий включая подмодули для подтягивания SDK - git clone --recurse-submodules
* Скачать установщик Shopscript CMS - https://developers.webasyst.ru/download/

* Добавить в /etc/hosts  127.0.0.1	shopscript.localhost.com
* Добавить shopscript.cfg в sites-enabled nginx
```sh
sudo cp [путь_до_проекта]/komtet-kassa-shopscript-delivery/configuration/shopscript.cfg /etc/nginx/sites-enabled
```
* Cоздать в корневом каталоге папку php
* Распаковать архив Shopscript CMS в папку php
* В файл /php/.htaccess добавить строчку: *php_value date.timezone 'Europe/Moscow*

* Запустить сборку проекта
```sh
make build
```

## Установка CMS
* Запустить контейнер
```sh
make start_web7
```
* Установить права на папку php
```sh
sudo chmod -R 777 php
```
* Проект будет доступен по адресу: http://shopscript.localhost.com;
* Настройки подключения к бд MySQL:
```sh
Сервер: mysql
Пользователь: devuser
Пароль: devpass
БД: test_db
```
## Доступные комманды из Makefile

* Собрать проект
```sh
make build
```
* Запустить проект на php5.6
```sh
make start_web5
```

* Запустить проект на php7.2
```sh
make start_web7
```

* Остановить проект
```sh
make stop
```

* Установить/Обновить модуль в cms
```sh
make update
```
