#!/bin/sh
set -eu

php artisan schedule:run --verbose --no-interaction
