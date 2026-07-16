#!/bin/sh
set -eu

PORT="${PORT:-10000}"
CONTAINER_ROLE="${CONTAINER_ROLE:-web}"
export PORT

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is not set. Add it to your Render environment variables before starting the app." >&2
    exit 1
fi

envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/http.d/default.conf

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

if [ ! -L public/storage ]; then
    php artisan storage:link >/dev/null 2>&1 || true
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ] && [ "${CONTAINER_ROLE}" = "web" ]; then
    php artisan migrate --force
fi

php artisan config:cache
php artisan view:cache

case "${CONTAINER_ROLE}" in
    web)
        php-fpm -D
        exec nginx -g 'daemon off;'
        ;;
    worker)
        exec php artisan queue:work --verbose --tries=1 --timeout=0
        ;;
    *)
        echo "Unsupported CONTAINER_ROLE: ${CONTAINER_ROLE}. Use 'web' or 'worker'." >&2
        exit 1
        ;;
esac
