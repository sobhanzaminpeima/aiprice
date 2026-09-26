#!/usr/bin/env bash
# Runs ON THE SERVER. Installs a release tarball into the Laravel app dir
# (whose public/ is the web root), after backing up the database and files.
# If any step fails, files and vendor/ are restored automatically.
#
#   bash remote-deploy.sh <laravel_app dir> <release.tar.gz>
#
# The release ships its own vendor/ (this host disables proc_open, so
# Composer can't run here). Preserved: .env, storage/, public/storage.
set -euo pipefail

APP="${1:?laravel app dir}"
TARBALL="${2:?release tarball}"
TS="$(date +%Y%m%d-%H%M%S)"
BACKUPS="$HOME/aiprice-backups"
mkdir -p "$BACKUPS"
chmod 700 "$BACKUPS"

PHP="${PHP_BIN:-php}"

echo "→ Pre-flight"
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' || { echo "✗ PHP 8.2+ required"; exit 1; }
[[ -f "$APP/.env" ]] || { echo "✗ $APP/.env missing"; exit 1; }
[[ -f "$APP/artisan" ]] || { echo "✗ $APP is not a Laravel app"; exit 1; }
tar -tzf "$TARBALL" ./vendor/autoload.php >/dev/null 2>&1 || { echo "✗ release has no vendor/ — rebuild it"; exit 1; }

env_get() { grep -E "^$1=" "$APP/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

echo "→ Backing up database"
DB_NAME="$(env_get DB_DATABASE)"; DB_USER="$(env_get DB_USERNAME)"; DB_PASS="$(env_get DB_PASSWORD)"; DB_HOST="$(env_get DB_HOST)"
MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --no-tablespaces -h "${DB_HOST:-localhost}" -u "$DB_USER" "$DB_NAME" | gzip > "$BACKUPS/db-$TS.sql.gz"
[[ -s "$BACKUPS/db-$TS.sql.gz" ]] || { echo "✗ database backup is empty — aborting"; exit 1; }
echo "  $BACKUPS/db-$TS.sql.gz ($(du -h "$BACKUPS/db-$TS.sql.gz" | cut -f1))"

echo "→ Backing up current files"
tar -czf "$BACKUPS/files-$TS.tar.gz" -C "$APP" --exclude='./vendor' --exclude='./vendor.prev' --exclude='./storage/logs' --exclude='./storage/framework' .
echo "  $BACKUPS/files-$TS.tar.gz"

cd "$APP"
rm -rf vendor.prev

rollback() {
  echo "✗ Deploy failed — restoring the previous release"
  cd "$APP"
  if [[ -d vendor.prev ]]; then rm -rf vendor && mv vendor.prev vendor; fi
  rm -rf app config routes resources database/migrations public/assets bootstrap/cache/*.php
  tar -xzf "$BACKUPS/files-$TS.tar.gz" -C "$APP"
  "$PHP" artisan config:clear >/dev/null 2>&1 || true
  "$PHP" artisan route:clear >/dev/null 2>&1 || true
  "$PHP" artisan up || true
  echo "  Files restored. Database backup (not auto-restored): $BACKUPS/db-$TS.sql.gz"
}
trap rollback ERR

echo "→ Maintenance mode"
"$PHP" artisan down --retry=30 || true

echo "→ Replacing code (keeping .env, storage, public/storage)"
STAGE="$(mktemp -d)"
tar -xzf "$TARBALL" -C "$STAGE"
mv vendor vendor.prev
# Drop code dirs so deleted files don't linger, then lay down the release.
rm -rf app bootstrap/cache/*.php config database/migrations routes resources public/assets
( cd "$STAGE" && tar -cf - --exclude='./.env' --exclude='./storage' --exclude='./public/storage' . ) | tar -xf - -C "$APP"
mkdir -p storage/app/public storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
rm -rf "$STAGE"

echo "→ Package discovery"
"$PHP" artisan package:discover --ansi >/dev/null

echo "→ Migrations"
"$PHP" artisan migrate --force

echo "→ Caches"
"$PHP" artisan optimize:clear >/dev/null
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan permission:cache-reset
"$PHP" artisan queue:restart || true

"$PHP" artisan up
trap - ERR
rm -rf vendor.prev
echo "✓ Deployed release $(cat RELEASE 2>/dev/null || echo '?') — backups in $BACKUPS"
