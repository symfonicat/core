#!/bin/sh
set -eu

# Self-install PHP dependencies on first boot so `git clone && docker compose up`
# works without requiring Composer (or even PHP) on the host. Set
# SYMFONICAT_AUTO_COMPOSER_INSTALL=0 to opt out (e.g. production images that
# bake vendor/ in at build time).
if [ "${SYMFONICAT_AUTO_COMPOSER_INSTALL:-1}" = "1" ] \
    && [ -f /app/composer.json ] \
    && [ ! -f /app/vendor/autoload.php ]; then
    echo "[symfonicat] vendor/ missing — running 'composer install' ..." >&2
    (cd /app && composer install --no-interaction --prefer-dist --no-progress)
fi

if [ "${SYMFONICAT_AUTO_BOOTSTRAP:-1}" = "1" ] && [ -f /app/bin/console ]; then
    php /app/bin/console symfonicat:bootstrap --no-interaction --wait="${SYMFONICAT_BOOTSTRAP_WAIT:-60}"
fi

exec docker-php-entrypoint "$@"
