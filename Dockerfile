FROM dunglas/frankenphp:builder-php8.4 AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=caddy:builder /usr/bin/xcaddy /usr/bin/xcaddy

RUN docker-php-source extract
RUN install-php-extensions \
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
    tidy \
    zip

ENV GEN_STUB_SCRIPT=/usr/local/lib/php/build/gen_stub.php
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /symfonicat

COPY composer.json composer.lock symfony.lock /symfonicat/

RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

COPY . /symfonicat

FROM php-base AS npm

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get update \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

FROM php-base AS builder

RUN php bin/console symfonicat:scriptling:copy | bash
RUN set -e; \
    find /symfonicat/extensions -type f -name '*.go' -exec dirname {} \; | sort -u | while read -r directory; do \
        if grep -Rqs 'export_php:function' "$directory"/*.go; then \
            GEN_STUB_SCRIPT="$GEN_STUB_SCRIPT" frankenphp extension-init "$directory"/*.go; \
        fi; \
        (cd "$directory" && go mod tidy); \
    done

ARG FRANKENPHP_REF=main
RUN cd /tmp \
    && curl -fsSL "https://github.com/dunglas/frankenphp/archive/refs/heads/${FRANKENPHP_REF}.tar.gz" | tar -xzf - \
    && mv "/tmp/frankenphp-${FRANKENPHP_REF}" /tmp/frankenphp-src

RUN set -e; \
    scriptling_flags="$(cd /symfonicat && php bin/console symfonicat:scriptling:bash)"; \
    cd /tmp/frankenphp-src \
    && CGO_ENABLED=1 \
       XCADDY_GO_BUILD_FLAGS="-ldflags='-w -s' -tags=nobadger,nomysql,nopgx" \
       CGO_CFLAGS="-D_GNU_SOURCE $(php-config --includes)" \
       CGO_LDFLAGS="$(php-config --ldflags) $(php-config --libs)" \
       xcaddy build \
         --output /usr/local/bin/frankenphp \
         --with github.com/dunglas/frankenphp=/tmp/frankenphp-src \
         --with github.com/dunglas/frankenphp/caddy=/tmp/frankenphp-src/caddy \
         --with github.com/dunglas/mercure/caddy \
         --with github.com/dunglas/vulcain/caddy \
         --with github.com/dunglas/caddy-cbrotli \
         $scriptling_flags

RUN rm -rf /tmp/frankenphp-src /root/.cache/go-build /root/.composer/cache /usr/src/php

FROM builder AS runtime

WORKDIR /symfonicat
