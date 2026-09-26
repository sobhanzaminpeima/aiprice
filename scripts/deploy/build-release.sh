#!/usr/bin/env bash
# Builds a deployable release tarball from the COMMITTED state of the repo,
# laid out like the server's Laravel app directory:
#
#   laravel_app/            ← tarball root = backend/ from git
#   laravel_app/public/     ← Laravel front controller + the Vue build;
#                             the SPA's index.html ships as public/spa.html,
#                             which routes/web.php serves for every non-API path.
#
# Never includes .env, vendor/ or storage contents, so no secrets ship.
#
#   scripts/deploy/build-release.sh   → build/aiprice-release-<sha>.tar.gz
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

if [[ -n "$(git status --porcelain -- backend frontend)" ]]; then
  echo "✗ Uncommitted changes in backend/ or frontend/ — commit first so the release matches git." >&2
  exit 1
fi

SHA="$(git rev-parse --short HEAD)"
OUT="$ROOT/build"
STAGE="$OUT/release-$SHA"
rm -rf "$STAGE" && mkdir -p "$STAGE"

echo "→ Building frontend ($SHA)"
# Reuse an existing install: `npm ci` deletes node_modules first, which fails
# half-way on Windows while a dev server holds files open.
( cd frontend && { [[ -x node_modules/.bin/vite ]] || npm ci --no-audit --no-fund; } && npx vite build --logLevel warn )

echo "→ Packing backend from git"
git archive --format=tar HEAD:backend | tar -x -C "$STAGE"
rm -rf "$STAGE/tests" "$STAGE/phpunit.xml"

echo "→ Installing PHP dependencies for the server (PHP ${SERVER_PHP:=8.3.33})"
# The host disables proc_open, so Composer can't run there — vendor/ ships in
# the release. Pinning the platform makes Composer fail here if any locked
# package needs a newer PHP than the server has.
COMPOSER_CMD="${COMPOSER_BIN:-composer}"
( cd "$STAGE" \
  && "$COMPOSER_CMD" config platform.php "$SERVER_PHP" \
  && "$COMPOSER_CMD" install --no-dev --optimize-autoloader --no-interaction --no-scripts --quiet \
  && "$COMPOSER_CMD" check-platform-reqs --no-dev --quiet 2>/dev/null || true )
[[ -f "$STAGE/vendor/autoload.php" ]] || { echo "✗ composer install failed" >&2; exit 1; }
# Keep the committed composer.json untouched in the release.
git show HEAD:backend/composer.json > "$STAGE/composer.json"

echo "→ Adding SPA to public/"
cp -R frontend/dist/. "$STAGE/public/"
mv "$STAGE/public/index.html" "$STAGE/public/spa.html"
echo "$SHA" > "$STAGE/RELEASE"

TARBALL="$OUT/aiprice-release-$SHA.tar.gz"
tar -czf "$TARBALL" -C "$STAGE" .
echo "✓ $TARBALL ($(du -h "$TARBALL" | cut -f1))"
