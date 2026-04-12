#!/bin/sh
set -eu

if [ "${SYMFONICAT_AUTO_BOOTSTRAP:-1}" = "1" ] && [ -f /app/bin/console ]; then
    php /app/bin/console symfonicat:bootstrap --no-interaction --wait="${SYMFONICAT_BOOTSTRAP_WAIT:-60}"
fi

exec docker-php-entrypoint "$@"
