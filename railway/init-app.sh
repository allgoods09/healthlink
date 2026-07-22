#!/bin/sh
set -eu

php artisan migrate --force
php artisan optimize:clear
php artisan storage:link || true
