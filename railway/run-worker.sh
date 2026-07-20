#!/usr/bin/env bash
set -euo pipefail

php artisan queue:work --verbose --tries=1 --timeout=0 --sleep=3
