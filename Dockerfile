# syntax=docker/dockerfile:1.7

FROM dunglas/frankenphp:builder-php8.4 AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    git \
    unzip \
    \
    autoconf \
    build-essential \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=caddy:builder /usr/bin/xcaddy /usr/bin/xcaddy
RUN docker-php-source extract

WORKDIR /symfonicat

ARG SRC=/usr/local/src
ARG GEN_STUB_SCRIPT=/usr/local/lib/php/build/gen_stub.php
ARG FRANKENPHP_REF=main

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

RUN set -eu; \
    ext_paths="$({ \
        php bin/console symfonicat:discover:ext:paths './'; \
        php bin/console symfonicat:discover:ext:paths 'core/'; \
        php bin/console symfonicat:discover:ext:paths 'vendor/**/**/'; \
    } | sort -u)"; \
    ext_names="$({ \
        php bin/console symfonicat:discover:ext:names './'; \
        php bin/console symfonicat:discover:ext:names 'core/'; \
        php bin/console symfonicat:discover:ext:names 'vendor/**/**/'; \
    } | sort -u)"; \
    mkdir -p "$EXT"; \
    printf '%s\n' "$ext_paths" | while IFS= read -r directory; do \
        [ -n "$directory" ] || continue; \
        ext_name="$(basename "$directory")"; \
        rm -rf "$EXT/$ext_name"; \
        cp -R "$directory" "$EXT/$ext_name"; \
        if grep -q '^PHP_ARG_ENABLE(' "$EXT/$ext_name/config.m4"; then \
            docker-php-ext-configure "$ext_name" --enable-"${ext_name//_/-}"; \
        elif grep -q '^PHP_ARG_WITH(' "$EXT/$ext_name/config.m4"; then \
            docker-php-ext-configure "$ext_name" --with-"${ext_name//_/-}"; \
        fi; \
    done; \
    if [ -n "$ext_names" ]; then \
        docker-php-ext-install $ext_names; \
    fi;

FROM php-base AS npm

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get update \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

FROM php-base AS builder

RUN cd $SRC \
    && curl -fsSL "https://github.com/dunglas/frankenphp/archive/refs/heads/${FRANKENPHP_REF}.tar.gz" | tar -xzf - \
    && mv "$SRC/frankenphp-${FRANKENPHP_REF}" $SRC/frankenphp-src

RUN --mount=type=tmpfs,target=/tmp \
    --mount=type=tmpfs,target=/root/.cache/go-build \
    set -e; \
    go_paths="$({ \
        cd /symfonicat && php bin/console symfonicat:discover:go:paths './'; \
        cd /symfonicat && php bin/console symfonicat:discover:go:paths 'core/'; \
        cd /symfonicat && php bin/console symfonicat:discover:go:paths 'vendor/**/**/'; \
    } | sort -u)"; \
    go_flags_file="$(mktemp)"; \
    : > "$go_flags_file"; \
    printf '%s\n' "$go_paths" | while IFS= read -r directory; do \
        [ -n "$directory" ] || continue; \
        if grep -Rqs 'export_php:function' "/symfonicat/$directory"/*.go; then \
            GEN_STUB_SCRIPT="$GEN_STUB_SCRIPT" frankenphp extension-init "/symfonicat/$directory"/*.go; \
        fi; \
        (cd "/symfonicat/$directory" && go mod tidy); \
        module_path="$(sed -n 's/^module //p' "/symfonicat/$directory/go.mod" | head -n 1)"; \
        if [ -n "$module_path" ]; then \
            printf '%s\n' "$(cat "$go_flags_file") --with $module_path=/symfonicat/$directory" > "$go_flags_file"; \
        fi; \
    done; \
    go_flags="$(cat "$go_flags_file")"; \
    rm -f "$go_flags_file"; \
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
         $go_flags

RUN rm -rf \
        $SRC/frankenphp-src \
        /root/.cache/go-build \
        /root/.composer/cache \
        /usr/src/php

FROM builder AS runtime

COPY Caddyfile /etc/frankenphp/Caddyfile
COPY docker-entrypoint.sh /usr/local/bin/symfonicat-entrypoint
RUN chmod +x /usr/local/bin/symfonicat-entrypoint

ENTRYPOINT ["/usr/local/bin/symfonicat-entrypoint"]
