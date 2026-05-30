# syntax=docker/dockerfile:1.7

FROM dunglas/frankenphp:builder-php8.4 AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    git \
    redis-server \
    unzip \
    \
    autoconf \
    build-essential \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=caddy:builder /usr/bin/xcaddy /usr/bin/xcaddy

WORKDIR /symfonicat

ARG SRC=/usr/local/src
ARG GEN_STUB_SCRIPT=/usr/local/lib/php/build/gen_stub.php
ARG FRANKENPHP_REF=main

ARG EXT=/usr/src/php/ext
ARG EXT_TMP=/tmp/native/ext

RUN docker-php-source extract
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

COPY native/ext $EXT_TMP
RUN set -eu; \
    EXT_PATHS="$(find vendor -path 'vendor/*/*/ext/*/config.m4' -printf '%h ' 2>/dev/null || true)"; \
    if [ -n "$EXT_PATHS" ]; then \
        for ext in $EXT_PATHS; do \
            cp -R "$ext" "$EXT_TMP"/; \
        done; \
    fi; \
    mkdir -p "$EXT"; \
    ls -ld "$EXT_TMP" "$EXT"; \
    cp -R "$EXT_TMP"/. "$EXT"/; \
    EXTS="$(find "$EXT_TMP" -mindepth 1 -maxdepth 1 -type d -printf '%f ')"; \
    find "$EXT" -mindepth 1 -maxdepth 1 -type d -printf ' - %f\n' | sort; \
    if [ -n "$EXTS" ]; then \
        docker-php-ext-install $EXTS; \
    fi;

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

RUN cd $SRC \
    && curl -fsSL "https://github.com/dunglas/frankenphp/archive/refs/heads/${FRANKENPHP_REF}.tar.gz" | tar -xzf - \
    && mv "$SRC/frankenphp-${FRANKENPHP_REF}" $SRC/frankenphp-src

RUN --mount=type=tmpfs,target=/tmp \
    --mount=type=tmpfs,target=/root/.cache/go-build \
    set -e; \
    scriptling_flags="$(cd /symfonicat && php bin/console symfonicat:scriptling:bash)"; \
    cd $SRC/frankenphp-src \
    && CGO_ENABLED=1 \
       XCADDY_GO_BUILD_FLAGS="-ldflags='-w -s' -tags=nobadger,nomysql,nopgx" \
       CGO_CFLAGS="-D_GNU_SOURCE $(php-config --includes)" \
       CGO_LDFLAGS="$(php-config --ldflags) $(php-config --libs)" \
       xcaddy build \
         --output /usr/local/bin/frankenphp \
         --with github.com/dunglas/frankenphp=$SRC/frankenphp-src \
         --with github.com/dunglas/frankenphp/caddy=$SRC/frankenphp-src/caddy \
         --with github.com/dunglas/mercure/caddy \
         --with github.com/dunglas/vulcain/caddy \
         --with github.com/dunglas/caddy-cbrotli \
         $scriptling_flags

RUN rm -rf \
        $EXT_TMP \
        $SRC/frankenphp-src \
        /root/.cache/go-build \
        /root/.composer/cache \
        /usr/src/php

FROM builder AS runtime

COPY Caddyfile /etc/frankenphp/Caddyfile
COPY docker-entrypoint.sh /usr/local/bin/symfonicat-entrypoint
RUN chmod +x /usr/local/bin/symfonicat-entrypoint

ENTRYPOINT ["/usr/local/bin/symfonicat-entrypoint"]
