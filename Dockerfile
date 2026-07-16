FROM php:8.4-cli-alpine AS vendor

WORKDIR /app

RUN apk add --no-cache \
        git \
        unzip \
        icu-libs \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        oniguruma \
        libxml2 \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        libxml2-dev \
        postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        dom \
        simplexml \
        xml \
        xmlreader \
        xmlwriter \
        zip \
    && apk del .build-deps

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --no-scripts

COPY . .

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --no-scripts


FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY . .

RUN npm run build


FROM php:8.4-fpm-alpine AS runtime

WORKDIR /var/www/html

RUN apk add --no-cache \
        nginx \
        gettext \
        curl \
        mariadb-client \
        postgresql-client \
        icu-libs \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        oniguruma \
        libxml2 \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        libxml2-dev \
        postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        dom \
        simplexml \
        xml \
        xmlreader \
        xmlwriter \
        zip \
    && apk del .build-deps

COPY --from=vendor /app ./
COPY --from=frontend /app/public/build ./public/build

COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY docker/php/render.ini /usr/local/etc/php/conf.d/render.ini
COPY docker/php-fpm/zz-render.conf /usr/local/etc/php-fpm.d/zz-render.conf
COPY docker/start-container.sh /usr/local/bin/start-container

RUN chmod +x /usr/local/bin/start-container \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        /run/nginx \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 10000

CMD ["/usr/local/bin/start-container"]
