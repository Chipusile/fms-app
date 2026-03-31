#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

echo "Waiting for PostgreSQL at ${DB_HOST:-postgres}:${DB_PORT:-5432}..."
until pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-fms}" >/dev/null 2>&1; do
  sleep 2
done

if [ ! -f vendor/autoload.php ] || { [ -f composer.lock ] && [ composer.lock -nt vendor/autoload.php ]; }; then
  composer install --no-interaction --prefer-dist
fi

if [ "${APP_KEY:-}" = "" ] && [ -f .env ] && ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force --no-interaction || true
fi

php artisan config:clear >/dev/null 2>&1 || true
php artisan route:clear >/dev/null 2>&1 || true
php artisan view:clear >/dev/null 2>&1 || true

exec "$@"
