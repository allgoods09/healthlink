#!/bin/sh
set -eu

php artisan queue:work --verbose --tries=1 --timeout=0 --sleep=3
