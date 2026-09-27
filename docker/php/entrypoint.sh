#!/bin/sh
# Prepares the Laravel app, then runs the service command (php-fpm, queue:work or schedule:work).
set -e
cd /var/www/backend

if [ "$APP_ENV" != "production" ] && [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

if [ -n "$DB_HOST" ]; then
  echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}…"
  # Use PDO (same driver as Laravel); the Alpine mysql client cannot speak caching_sha2_password.
  for i in $(seq 1 60); do
    if php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0); } catch (Throwable $e) { exit(1); }' 2>/dev/null; then
      echo "MySQL is ready."
      break
    fi
    sleep 2
  done
fi

# Only the main backend container runs one-time tasks.
if [ "$CONTAINER_ROLE" = "app" ]; then
  if [ -z "$APP_KEY" ] && ! grep -q '^APP_KEY=base64' .env 2>/dev/null; then
    echo "APP_KEY is missing. Run: docker compose exec backend php artisan key:generate" >&2
  fi
  php artisan storage:link --quiet 2>/dev/null || true
  if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
    php artisan migrate --force
  fi
  if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
  fi
fi

exec "$@"
