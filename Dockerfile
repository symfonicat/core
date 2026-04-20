FROM dunglas/frankenphp:php8.4

RUN install-php-extensions \
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
