#!/bin/sh

set -eu

log() {
    printf '[symfonicat-init] %s\n' "$*"
}

ensure_vendor() {
    if [ -f /symfonicat/vendor/autoload_runtime.php ]; then
        return 0
    fi

    log "vendor/autoload_runtime.php is missing, running composer install"
    composer install --no-interaction --prefer-dist --no-progress --no-scripts
}

tls_dir=/run/symfonicat/tls
caddy_dir=/run/symfonicat/caddy
tls_snippet="$caddy_dir/tls.caddy"
fullchain_file="$tls_dir/fullchain.pem"
privkey_file="$tls_dir/privkey.pem"

mkdir -p "$tls_dir" "$caddy_dir"

ensure_vendor

if [ -n "${AWS_ECS_TLS_FULLCHAIN_B64:-}" ] && [ -n "${AWS_ECS_TLS_PRIVATE_KEY_B64:-}" ]; then
    log "writing ACM TLS material from ECS env"
    umask 077
    printf '%s' "$AWS_ECS_TLS_FULLCHAIN_B64" | base64 -d > "$fullchain_file"
    printf '%s' "$AWS_ECS_TLS_PRIVATE_KEY_B64" | base64 -d > "$privkey_file"
    chmod 600 "$fullchain_file" "$privkey_file"
    cat > "$tls_snippet" <<EOF
tls $fullchain_file $privkey_file
EOF
else
    log "using internal TLS certificate"
    cat > "$tls_snippet" <<'EOF'
tls internal
EOF
fi

log "starting redis-server on 127.0.0.1:6379"
redis-server \
    --bind 127.0.0.1 \
    --port 6379 \
    --save "" \
    --appendonly no \
    --logfile "" \
    --loglevel notice &
redis_pid=$!
php_pid=""

cleanup() {
    if [ -n "${php_pid:-}" ]; then
        kill "$php_pid" 2>/dev/null || true
        wait "$php_pid" 2>/dev/null || true
    fi

    kill "$redis_pid" 2>/dev/null || true
    wait "$redis_pid" 2>/dev/null || true
}

trap cleanup INT TERM EXIT

log "waiting for redis-server to accept connections"
if command -v redis-cli >/dev/null 2>&1; then
    for _ in 1 2 3 4 5 6 7 8 9 10; do
        if redis-cli -h 127.0.0.1 -p 6379 ping >/dev/null 2>&1; then
            log "redis-server is ready"
            break
        fi

        sleep 0.2
    done
fi

log "starting frankenphp"
frankenphp run --config /etc/frankenphp/Caddyfile &
php_pid=$!

set +e
log "waiting for frankenphp to exit"
wait "$php_pid"
status=$?
set -e

log "frankenphp exited with status ${status}"
cleanup
log "container shutdown complete"
exit "$status"
