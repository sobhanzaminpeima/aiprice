# AIPrice.app

AI-powered vehicle and property marketplace for North Cyprus. AIPrice combines marketplace search, seller workflows, price intelligence, trust scoring, subscriptions, billing, and a permission-aware administration suite in one Laravel + Vue application.

**Production:** [https://aiprice.app](https://aiprice.app)<br>
**Admin:** [https://aiprice.app/admin](https://aiprice.app/admin)

> The repository does not contain production credentials. Seeded accounts are intended for local development only and must be changed before any public deployment.

## Highlights

- Vehicle, property, and rental listings with search, filters, maps, favorites, inquiries, and responsive detail pages
- AI price estimate, deal score, trust score, demand score, investment score, ranking, alternatives, and market insights
- Seller dashboard for listing management, analytics, messages, KYC, support, invoices, and subscriptions
- Role- and permission-aware admin suite covering users, listings, KYC, finance, fraud, support, content, reports, advertisements, analytics, LLMs, competitors, and social publishing
- Secure authentication with Sanctum, rate limits, email verification, password reset, forced password changes, and token revocation
- Queue-backed AI analysis, email and in-app notifications, competitor crawling, and scheduled maintenance
- Stripe-ready billing abstraction, invoice history, subscription plans, and webhook processing
- SEO foundations including canonical metadata, Open Graph, JSON-LD, `robots.txt`, and dynamic sitemap
- Mobile-only bottom navigation and Lucide icon system; the desktop layout does not render the bottom bar

## Technology

| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.2+, MySQL 8 |
| Frontend | Vue 3, TypeScript 6, Vite 8, Pinia, Tailwind CSS 4 |
| Authentication | Laravel Sanctum |
| Authorization | Spatie Laravel Permission plus endpoint permission middleware |
| AI | OpenAI-compatible services; production default is configurable |
| Billing | Payment gateway abstraction with Stripe Checkout support |
| Background work | Laravel queues and scheduler |
| Maps | Leaflet |

## Repository layout

```text
.
├── backend/                    Laravel API and production SPA host
│   ├── app/                    Controllers, models, jobs, services, notifications
│   ├── database/               21 migrations, factories, and seeders
│   ├── routes/                 API, web fallback, and scheduled commands
│   └── tests/                  Feature and unit tests
├── frontend/                   Vue single-page application
│   └── src/
│       ├── views/              Public, dashboard, auth, and 25 admin views
│       ├── components/         Navigation, listings, UI, metadata, notifications
│       ├── stores/             Pinia application state
│       └── router/             Route guards and permission-aware admin navigation
├── scripts/deploy/             Reproducible release and remote deployment scripts
├── docs/                       Deployment and security documentation
├── DEVELOPMENT.md              Local development and architecture guide
├── CHANGELOG.md                Release history
└── SECURITY.md                 Vulnerability reporting and secure operation
```

## Local setup

### Requirements

- PHP 8.2 or newer and Composer 2
- Node.js `^22.18.0` or `>=24.12.0`
- MySQL 8

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Set database, mail, frontend URL, AI provider, and optional payment credentials in `backend/.env`. Never commit a filled `.env` file.

### Frontend

```bash
cd frontend
npm ci
npm run dev
```

Vite serves the app at `http://localhost:3000` and proxies `/api` to `http://localhost:8000`.

### Workers and scheduled jobs

```bash
cd backend
php artisan queue:work --queue=default,ai,crawl
php artisan schedule:work
```

Without a worker, queued AI analysis, crawls, emails, and notifications remain pending.

## Environment variables

Start with [`backend/.env.example`](backend/.env.example). Important production values include:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://aiprice.app
FRONTEND_URL=https://aiprice.app

DB_CONNECTION=mysql
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database

MAIL_MAILER=smtp
OPENAI_API_KEY=
PAYMENT_GATEWAY=stripe
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
```

The deploy-ready template is in [`scripts/deploy/production.env.example`](scripts/deploy/production.env.example).

## Quality checks

```bash
# Backend
cd backend
php artisan test

# Frontend
cd frontend
npm run type-check
npm run lint
npm run build
```

The current backend suite contains 85 tests with 408 assertions. Run the checks again whenever dependencies or application code change.

## API overview

All JSON endpoints use the `/api/v1` prefix.

| Area | Examples |
|---|---|
| Authentication | `POST /auth/register`, `POST /auth/login`, `POST /auth/forgot-password` |
| Marketplace | `GET /listings`, `GET /listings/{slug}`, `GET /listings-map` |
| Seller | `POST /listings`, `GET /my-listings`, `POST /listings/{id}/ai-analyze` |
| Account | `/profile`, `/favorites`, `/notifications`, `/support-tickets`, `/kyc` |
| Billing | `/billing/status`, `/billing/checkout`, `/billing/invoices` |
| Administration | `/admin/*`, protected by Sanctum, password freshness, role, and permission checks |

For architecture details, local seeded data, role behavior, and troubleshooting, see [DEVELOPMENT.md](DEVELOPMENT.md).

## Deployment

Production releases are assembled with the tracked deployment scripts:

```bash
COMPOSER_BIN=composer bash scripts/deploy/build-release.sh
```

The remote script performs preflight checks, database and file backups, maintenance mode, code replacement, migrations, cache rebuilds, queue restart, and rollback on failure. See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) before deploying.

## Documentation

- [Developer setup and architecture](DEVELOPMENT.md)
- [Deployment and rollback](docs/DEPLOYMENT.md)
- [Launch deployment checklist](docs/DEPLOYMENT_CHECKLIST.md)
- [Security policy and production checklist](SECURITY.md)
- [Release history](CHANGELOG.md)
- [Backend entry point](backend/README.md)

## License

Proprietary project. All rights reserved by the project owner unless a separate written license is provided.
