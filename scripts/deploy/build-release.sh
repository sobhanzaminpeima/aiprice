#!/usr/bin/env bash
# Builds a deployable release tarball from the COMMITTED state of the repo.
#
#   scripts/deploy/build-release.sh            → build/aiprice-release-<sha>.tar.gz
#
# Contents (extracted into the web root, e.g. ~/domains/aiprice.app/public_html):
#   index.html, assets/ …   the Vue SPA build
#   .htaccess                routing + security headers (scripts/deploy/htaccess.root)
#   backend/                 Laravel source via `git archive` — never .env,
#                            vendor/ or storage contents, so no secrets ship.
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
( cd frontend && npm ci --no-audit --no-fund --silent && npx vite build --logLevel warn )
cp -R frontend/dist/. "$STAGE/"
cp scripts/deploy/htaccess.root "$STAGE/.htaccess"

echo "→ Packing backend from git"
git archive --format=tar HEAD backend | tar -x -C "$STAGE"
rm -rf "$STAGE/backend/tests" "$STAGE/backend/phpunit.xml"
echo "$SHA" > "$STAGE/backend/RELEASE"

TARBALL="$OUT/aiprice-release-$SHA.tar.gz"
tar -czf "$TARBALL" -C "$STAGE" .
echo "✓ $TARBALL ($(du -h "$TARBALL" | cut -f1))"
