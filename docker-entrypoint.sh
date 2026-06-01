#!/bin/sh

set -eu

log() {
    printf '[symfonicat-init] %s\n' "$*"
}

tls_dir=/run/symfonicat/tls
caddy_dir=/run/symfonicat/caddy
tls_snippet="$caddy_dir/tls.caddy"
fullchain_file="$tls_dir/fullchain.pem"
privkey_file="$tls_dir/privkey.pem"

mkdir -p "$tls_dir" "$caddy_dir"

#composer install

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
php_pid=""

cleanup() {
    if [ -n "${php_pid:-}" ]; then
        kill "$php_pid" 2>/dev/null || true
        wait "$php_pid" 2>/dev/null || true
    fi
}

trap cleanup INT TERM EXIT

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
