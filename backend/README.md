# AIPrice backend

Laravel 12 API and production web host for [AIPrice.app](https://aiprice.app).

The backend provides authentication, marketplace and seller APIs, administration endpoints, AI orchestration, queues, notifications, billing, reports, and the SPA fallback used in production.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Run background work in separate terminals:

```bash
php artisan queue:work --queue=default,ai,crawl
php artisan schedule:work
```

## Tests

```bash
php artisan test
```

See the repository [README](../README.md), [developer guide](../DEVELOPMENT.md), [deployment guide](../docs/DEPLOYMENT.md), and [security policy](../SECURITY.md) for the complete documentation.
