#!/usr/bin/env bash
set -euo pipefail

php artisan optimize:clear
php artisan storage:link || true
php artisan migrate --force
