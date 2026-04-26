FROM dunglas/frankenphp:php8.4

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl nodejs npm \
    && npm install -g n \
    && n latest \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    @composer \
    intl \
    opcache \
    pdo_pgsql \
    redis \
    tidy

COPY docker/entrypoint.sh /usr/local/bin/symfonicat-entrypoint
RUN chmod +x /usr/local/bin/symfonicat-entrypoint

WORKDIR /app

ENTRYPOINT ["symfonicat-entrypoint"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
