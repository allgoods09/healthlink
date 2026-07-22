#!/bin/sh
set -eu

php artisan optimize:clear
php artisan storage:link || true
php artisan migrate --force
