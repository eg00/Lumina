#!/bin/sh
set -e
echo "==> Running database migrations..."
php artisan migrate --force
echo "==> Ensuring storage link exists..."
php artisan storage:link --force || true
echo "==> Running Lumina initialization (admin, settings, media sources)..."
php artisan init
echo "==> Starting FrankenPHP application server..."

if [ $# -eq 0 ]; then
    set -- frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile
fi

exec docker-php-entrypoint "$@"
