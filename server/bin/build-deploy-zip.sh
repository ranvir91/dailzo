#!/usr/bin/env bash
# Builds dailzo-deploy.zip: a production-ready copy of the Laravel app —
# code + a `vendor/` built with `composer install --no-dev` (so the server
# itself never needs composer) — for GoDaddy cPanel deploys where git isn't
# available on the server. See docs/deployment-godaddy.md.
#
# Deliberately excluded from the zip:
#   - .env / .env.*        real secrets live only on the server, never here
#   - tests/, .git/        not needed at runtime
#   - storage/logs, storage/framework/{cache,sessions,views,testing},
#     bootstrap/cache      stale local cache must never ship; the server
#                          regenerates its own after extracting
#   - public/uploads/*     real uploaded product images live on the server;
#                          re-extracting must never overwrite them (only
#                          uploads/.htaccess, the CORS fix, ships)
#
# Usage: server/bin/build-deploy-zip.sh [output-zip-path]
#   Default output: <repo-root>/dailzo-deploy.zip
set -euo pipefail

SERVER_DIR="$(cd "$(dirname "$0")/.." && pwd)"
REPO_ROOT="$(cd "$SERVER_DIR/.." && pwd)"
OUT_ZIP="${1:-$REPO_ROOT/dailzo-deploy.zip}"
BUILD_DIR="$(mktemp -d /tmp/dailzo-server-build.XXXXXX)"
trap 'rm -rf "$BUILD_DIR"' EXIT

echo "==> Copying app code (excluding vendor/, .env, tests, caches, uploads)"
rsync -a --delete \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='tests' \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='storage/logs' \
    --exclude='storage/framework/cache' \
    --exclude='storage/framework/sessions' \
    --exclude='storage/framework/views' \
    --exclude='storage/framework/testing' \
    --exclude='bootstrap/cache' \
    --exclude='public/uploads' \
    --exclude='public/build' \
    --exclude='*.sqlite' \
    --exclude='.phpunit.result.cache' \
    "$SERVER_DIR"/ "$BUILD_DIR"/

echo "==> Recreating required empty runtime directories"
mkdir -p "$BUILD_DIR"/storage/framework/{cache,sessions,views,testing}
mkdir -p "$BUILD_DIR"/storage/app/public "$BUILD_DIR"/storage/logs
mkdir -p "$BUILD_DIR"/bootstrap/cache
for d in storage/framework/cache storage/framework/sessions storage/framework/views \
         storage/framework/testing storage/logs bootstrap/cache; do
    touch "$BUILD_DIR/$d/.gitignore"
done

echo "==> Restoring public/uploads/.htaccess (CORS header) without the real images"
mkdir -p "$BUILD_DIR"/public/uploads
cp "$SERVER_DIR"/public/uploads/.htaccess "$BUILD_DIR"/public/uploads/.htaccess

echo "==> Removing dev-only tooling files not needed at runtime"
rm -f "$BUILD_DIR"/vite.config.js "$BUILD_DIR"/postcss.config.js \
      "$BUILD_DIR"/tailwind.config.js "$BUILD_DIR"/package.json \
      "$BUILD_DIR"/phpunit.xml
# bin/deploy.sh runs ON the server after extracting — keep it. Only this
# build script and the local-docker-only test-db helper are dev-machine-only.
rm -f "$BUILD_DIR"/bin/build-deploy-zip.sh "$BUILD_DIR"/bin/setup-test-db.sh

echo "==> composer install --no-dev (in the copy — your working vendor/ is untouched)"
(cd "$BUILD_DIR" && composer install --no-dev --optimize-autoloader --no-interaction)

echo "==> Zipping"
rm -f "$OUT_ZIP"
(cd "$BUILD_DIR" && zip -r -q "$OUT_ZIP" .)

echo "==> Done: $OUT_ZIP ($(du -h "$OUT_ZIP" | cut -f1))"
