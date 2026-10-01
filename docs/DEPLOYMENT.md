# Deployment and rollback

AIPrice is deployed as a Laravel application whose `public/` directory is the web root. The compiled Vue SPA is copied into Laravel's public assets by the release builder.

## Production requirements

- PHP 8.2 or newer
- MySQL 8-compatible database
- HTTPS with secure cookies
- SMTP account for verification, password-reset, and notification mail
- Cron access for Laravel's scheduler
- Existing production `.env` stored outside version control

## Build a release

From the repository root on a trusted build machine:

```bash
COMPOSER_BIN=composer bash scripts/deploy/build-release.sh
```

The script installs production backend dependencies, builds the frontend, copies SPA output into the Laravel public directory, and creates `build/aiprice-release-<sha>.tar.gz`.

## Deploy on the server

Upload both the release archive and `scripts/deploy/remote-deploy.sh`, then run:

```bash
bash remote-deploy.sh /absolute/path/to/laravel_app /absolute/path/to/aiprice-release-<sha>.tar.gz
```

The remote script:

1. verifies PHP, `.env`, Artisan, and bundled Composer dependencies;
2. creates timestamped database and application backups;
3. enables maintenance mode;
4. replaces application code while preserving `.env`, storage, and public uploads;
5. runs package discovery and database migrations;
6. rebuilds Laravel caches and permission cache;
7. restarts queue workers and returns the site to service;
8. restores the previous files automatically if a deployment step fails.

Backups are stored in `~/aiprice-backups` and should also be copied to independent storage according to the retention policy.

## Shared-host scheduler

Some shared hosts disable `proc_open` and cannot spawn a queue worker from `schedule:run`. Set:

```env
QUEUE_WORKER_VIA_SCHEDULER=true
```

Then run the in-process scheduler every minute:

```cron
* * * * * /usr/bin/php /absolute/path/to/laravel_app/artisan schedule:run-inline
```

## Post-deployment checks

```bash
php artisan about
php artisan migrate:status
php artisan route:list --path=api/v1
php artisan schedule:list
```

Also verify the home page, login, one listing detail page, `/admin`, sitemap, email delivery, queue processing, and browser console at desktop and mobile breakpoints.

## Rollback

If the automated rollback cannot complete, keep the application in maintenance mode, restore the latest file archive from `~/aiprice-backups`, restore the matching database backup only when the migration changed data incompatibly, rebuild caches, and run `php artisan up` after verification.

Never overwrite production `.env`, uploaded files, or backups with repository content.
