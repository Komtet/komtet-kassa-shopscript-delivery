FROM php:7.4.33-apache as php7
RUN docker-php-ext-install mysqli

RUN apt-get update && apt-get install -y libpng-dev zlib1g-dev
RUN apt-get install -y \
    libwebp-dev \
    libjpeg62-turbo-dev \
    libpng-dev libxpm-dev \
    libfreetype6-dev \
    libpng-dev zlib1g-dev

RUN docker-php-ext-install gd

RUN docker-php-ext-configure gd \
    --enable-gd \
    --with-webp \
    --with-jpeg \
    --with-xpm \
    --with-freetype

WORKDIR /var/www/html
COPY php .

FROM php:8.0-apache as php8
RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    libzip-dev \
    && docker-php-ext-install mysqli zip

    WORKDIR /var/www/html
    COPY php .
