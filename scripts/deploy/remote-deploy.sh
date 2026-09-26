#!/usr/bin/env bash
# Runs ON THE SERVER. Installs a release tarball into the web root with a
# database + files backup first, and rolls back files automatically if any
# step fails.
#
#   bash remote-deploy.sh <webroot> <release.tar.gz>
#
# Keeps backend/.env and backend/storage from the current install.
set -euo pipefail

WEBROOT="${1:?webroot}"
TARBALL="${2:?release tarball}"
BACKEND="$WEBROOT/backend"
TS="$(date +%Y%m%d-%H%M%S)"
BACKUPS="$HOME/aiprice-backups"
mkdir -p "$BACKUPS"

PHP="${PHP_BIN:-php}"
COMPOSER="${COMPOSER_BIN:-composer}"

echo "→ Pre-flight"
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' || { echo "✗ PHP 8.2+ required"; exit 1; }
[[ -f "$BACKEND/.env" ]] || { echo "✗ $BACKEND/.env missing — create it before deploying"; exit 1; }
command -v "$COMPOSER" >/dev/null || { echo "✗ composer not found (set COMPOSER_BIN)"; exit 1; }

env_get() { grep -E "^$1=" "$BACKEND/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

echo "→ Backing up database"
DB_NAME="$(env_get DB_DATABASE)"; DB_USER="$(env_get DB_USERNAME)"; DB_PASS="$(env_get DB_PASSWORD)"; DB_HOST="$(env_get DB_HOST)"
MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --no-tablespaces -h "${DB_HOST:-localhost}" -u "$DB_USER" "$DB_NAME" | gzip > "$BACKUPS/db-$TS.sql.gz"
echo "  $BACKUPS/db-$TS.sql.gz ($(du -h "$BACKUPS/db-$TS.sql.gz" | cut -f1))"

echo "→ Backing up current files"
tar -czf "$BACKUPS/files-$TS.tar.gz" -C "$WEBROOT" \
  --exclude='./backend/vendor' --exclude='./backend/storage/logs' --exclude='./backend/storage/framework' .
echo "  $BACKUPS/files-$TS.tar.gz"

rollback() {
  echo "✗ Deploy failed — restoring files from $BACKUPS/files-$TS.tar.gz"
  tar -xzf "$BACKUPS/files-$TS.tar.gz" -C "$WEBROOT"
  ( cd "$BACKEND" && "$COMPOSER" install --no-dev --no-interaction --quiet || true; "$PHP" artisan up || true )
  echo "  Files restored. Database backup (not auto-restored): $BACKUPS/db-$TS.sql.gz"
}
trap rollback ERR

echo "→ Maintenance mode"
( cd "$BACKEND" && "$PHP" artisan down --retry=30 || true )

echo "→ Replacing code (keeping .env and storage)"
STAGE="$(mktemp -d)"
tar -xzf "$TARBALL" -C "$STAGE"
# Remove old SPA assets so stale hashed bundles don't accumulate.
rm -rf "$WEBROOT/assets"
# Copy everything except backend/.env and backend/storage.
( cd "$STAGE" && tar -cf - --exclude='./backend/.env' --exclude='./backend/storage' . ) | tar -xf - -C "$WEBROOT"
mkdir -p "$BACKEND/storage/app/public" "$BACKEND/storage/framework/"{cache,sessions,views} "$BACKEND/storage/logs"
rm -rf "$STAGE"

cd "$BACKEND"
echo "→ Dependencies"
"$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --quiet

echo "→ Migrations"
"$PHP" artisan migrate --force

echo "→ Caches"
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan event:cache
"$PHP" artisan permission:cache-reset
"$PHP" artisan storage:link 2>/dev/null || true
"$PHP" artisan queue:restart || true

"$PHP" artisan up
trap - ERR
echo "✓ Deployed release $(cat "$BACKEND/RELEASE" 2>/dev/null || echo '?') — backups in $BACKUPS"
