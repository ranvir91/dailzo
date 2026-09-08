#!/usr/bin/env bash
# Post-deploy steps for the Dailzo Laravel app. Run from server/ after pulling
# new code. See docs/deployment-godaddy.md for the full first-time setup.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> composer install"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> migrate"
php artisan migrate --force

echo "==> cache config / routes / views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> filament optimize"
php artisan filament:optimize

echo "==> done"
