# syntax=docker/dockerfile:1.7

FROM dunglas/frankenphp:builder-php8.4 AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    git \
    unzip \
    autoconf \
    build-essential \
    pkg-config \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
    inotify-tools \
    nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=caddy:builder /usr/bin/xcaddy /usr/bin/xcaddy
RUN docker-php-source extract

WORKDIR /symfonicat

ARG SRC=/usr/local/src
ARG GEN_STUB_SCRIPT=/usr/local/lib/php/build/gen_stub.php
ARG FRANKENPHP_REF=main

RUN mkdir -p "$SRC" \
    && cd "$SRC" \
    && curl -fsSL "https://github.com/dunglas/frankenphp/archive/refs/heads/${FRANKENPHP_REF}.tar.gz" | tar -xzf - \
    && mv "$SRC/frankenphp-${FRANKENPHP_REF}" "$SRC/frankenphp-src"

ARG EXT=/usr/src/php/ext
RUN EXT_CORE="\
        apcu \
        bcmath \
        curl \
        exif \
        fileinfo \
        gd \
        igbinary \
        imagick \
        intl \
        mbstring \
        msgpack \
        opcache \
        pcntl \
        pdo_pgsql \
        posix \
        redis \
        sockets \
        sodium \
        tidy \
        zip"; \
    if [ -n "$EXT_CORE" ]; then \
        docker-php-ext-configure $EXT_CORE; \
        install-php-extensions $EXT_CORE; \
    fi

ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock symfony.lock /symfonicat/
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts --apcu-autoloader
RUN composer dump-autoload --no-interaction

COPY . /symfonicat

RUN /symfonicat/bin/native-build

FROM php-base AS npm

FROM php-base AS runtime

COPY Caddyfile /etc/frankenphp/Caddyfile
COPY docker-entrypoint.sh /usr/local/bin/symfonicat-entrypoint
RUN chmod +x /usr/local/bin/symfonicat-entrypoint

ENTRYPOINT ["/usr/local/bin/symfonicat-entrypoint"]
