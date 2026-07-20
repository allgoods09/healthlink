#!/usr/bin/env bash
set -euo pipefail

php artisan schedule:run --verbose --no-interaction
