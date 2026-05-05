FROM dunglas/frankenphp:php8.4

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl nodejs npm \
    && npm install -g n \
    && n latest \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    @composer \
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

RUN { \
        echo 'apc.enable_cli=1'; \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.interned_strings_buffer=32'; \
        echo 'opcache.max_accelerated_files=30000'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.validate_timestamps=1'; \
    } > /usr/local/etc/php/conf.d/symfonicat-performance.ini

COPY docker/entrypoint.sh /usr/local/bin/symfonicat-entrypoint
RUN chmod +x /usr/local/bin/symfonicat-entrypoint

WORKDIR /symfonicat

ENTRYPOINT ["symfonicat-entrypoint"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
