# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# CoffeeProject PHP image
#   base  -> system packages + PHP extensions (shared by dev and a future prod)
#   dev   -> dev php.ini, Composer, non-root user matching the host UID/GID
#   node  -> dev + Node LTS, for Vite (the Wayfinder Vite plugin runs artisan)
# ---------------------------------------------------------------------------

FROM composer:2 AS composer
FROM node:lts-trixie-slim AS nodejs

FROM php:8.5-fpm AS base

LABEL com.coffeeproject.project="CoffeeProject" \
      com.coffeeproject.service="app" \
      org.opencontainers.image.title="coffeeproject/php" \
      org.opencontainers.image.description="CoffeeProject PHP-FPM runtime"

# System libraries required to build the PHP extensions, plus runtime tools.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions. mbstring and OPcache are compiled into the PHP 8.5 base image.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        intl \
        zip \
        bcmath \
        pcntl \
        gd \
        exif

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
FROM base AS dev

ARG UID=1000
ARG GID=1000

COPY --from=composer /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini     /usr/local/etc/php/conf.d/zz-coffeeproject.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

# Non-root user whose IDs match the host user, so files written to src/ are
# owned by the developer. FPM workers run as this user too.
RUN (getent group "${GID}" || groupadd --gid "${GID}" coffeeproject) \
    && useradd --uid "${UID}" --gid "${GID}" --create-home --shell /bin/bash coffeeproject \
    && sed -i 's/^user = www-data/user = coffeeproject/; s/^group = www-data/group = coffeeproject/' \
        /usr/local/etc/php-fpm.d/www.conf \
    && chown -R "${UID}:${GID}" /var/www/html

ENV COMPOSER_HOME=/home/coffeeproject/.composer

USER coffeeproject

EXPOSE 9000
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
FROM dev AS node

LABEL com.coffeeproject.service="node"

USER root
COPY --from=nodejs /usr/local/bin/node /usr/local/bin/node
COPY --from=nodejs /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && ln -s ../lib/node_modules/corepack/dist/corepack.js /usr/local/bin/corepack \
    && node --version && npm --version \
    && mkdir -p /var/www/html/node_modules \
    && chown "${UID}:${GID}" /var/www/html/node_modules
# ^ a fresh named volume copies this directory's ownership, so npm can write.
USER coffeeproject

EXPOSE 5175
CMD ["npm", "run", "dev"]
