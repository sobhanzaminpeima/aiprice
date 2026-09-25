# AIPrice.app — North Cyprus AI Marketplace

> AI-powered price intelligence for cars & properties in North Cyprus.  
> Every listing is scored, analyzed, and verified by OpenAI GPT-4o Mini.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 · PHP 8.2 · MySQL |
| Frontend | Vue 3 · TypeScript · Pinia · TailwindCSS v4 |
| AI | OpenAI GPT-4o Mini (price analysis) |
| Auth | Laravel Sanctum (Bearer token) |
| Roles | Spatie Permissions |

---

## Features

- **AI Battery Score** — every listing gets a 0-100 price score vs market comparables
- **AI Trust Score** — confidence score based on data quality and market depth  
- **AI Investment Score** — ROI potential for properties and vehicles
- **Top 10 Sponsored** — paid advertisement slots with click tracking
- **Mega Navigation** — Cars & Properties dropdown with body types, brands, cities
- **Seller Dashboard** — multi-photo upload, AI price estimate before listing
- **Admin Panel** — 18 sections: listings, users, AI insights, LLM management, fraud, analytics, subscriptions, advertisements
- **LLM Management** — switch models (GPT-4o / GPT-4o Mini / GPT-3.5), test connection, batch re-analyze
- **Subscription Plans** — Free / Starter / Pro / Agency tiers

---

## Quick Start

### Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env
# Set DB_DATABASE, DB_USERNAME, DB_PASSWORD in .env
# Set OPENAI_API_KEY in .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000
```

### Frontend (Vue)

```bash
cd frontend
npm install
npm run dev
# Runs on http://localhost:5173
# Proxies /api → http://localhost:8000
```

---

## Environment Variables

### Backend `.env`

```env
APP_NAME="AIPrice.app"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aiprice
DB_USERNAME=root
DB_PASSWORD=

OPENAI_API_KEY=sk-proj-...
OPENAI_MODEL=gpt-4o-mini
```

---

## Project Structure

```
AIPrice/
├── backend/                  # Laravel 12 API
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── Api/          # Public API (listings, auth, market stats)
│   │   │   └── Admin/        # Admin management controllers
│   │   ├── Models/           # Listing, Vehicle, Property, Advertisement, ...
│   │   └── Services/
│   │       └── AiPriceService.php   # OpenAI integration
│   ├── database/
│   │   ├── migrations/       # Full schema
│   │   └── seeders/          # Demo data (32 listings)
│   └── routes/api.php        # All API routes
│
└── frontend/                 # Vue 3 + TypeScript
    └── src/
        ├── views/
        │   ├── HomeView.vue             # Hero, AI sections, subscriptions
        │   ├── marketplace/             # Cars, Properties, Search, Detail
        │   ├── dashboard/               # Seller dashboard
        │   └── admin/                   # 18 admin views
        ├── components/
        │   └── listings/ListingCard.vue # AI battery, trust bar, specs
        ├── layouts/
        │   ├── MainLayout.vue           # Mega-nav header
        │   └── AdminLayout.vue          # Admin sidebar
        ├── stores/auth.ts               # Pinia auth store
        ├── api/client.ts                # Axios with Sanctum
        └── types/index.ts               # TypeScript interfaces
```

---

## Admin Panel Sections

| Section | Path |
|---|---|
| Overview | `/admin/overview` |
| Live Analytics | `/admin/live-analytics` |
| AI Insights | `/admin/ai-insights` |
| LLM Management | `/admin/llm-management` |
| All Listings | `/admin/listings` |
| Users | `/admin/users` |
| Advertisements | `/admin/advertisements` |
| Subscriptions | `/admin/subscriptions` |
| Market Analytics | `/admin/market-analytics` |
| Financial | `/admin/financial` |
| Fraud Detection | `/admin/fraud` |
| Settings | `/admin/settings` |

---

## API Overview

```
POST /api/v1/auth/register
POST /api/v1/auth/login
GET  /api/v1/listings?type=vehicle&city=Girne&sort=price_asc
GET  /api/v1/listings/{slug}
GET  /api/v1/subscription-plans
GET  /api/v1/advertisements?slot=top10
GET  /api/v1/market/stats

POST /api/v1/listings/create          (auth)
POST /api/v1/listings/{id}/upload-images  (auth)
POST /api/v1/listings/{id}/ai-analyze (auth)

GET  /api/v1/admin/listings           (admin)
POST /api/v1/admin/llm/test           (admin)
POST /api/v1/admin/listings/batch-analyze (admin)
```

---

## Design System

```
Background:   #05070b
Cards:        #0d1117
Teal:         #6fffd2
Cyan:         #56d8ff
Violet:       #a78bfa
Text Primary: #f0f6fc
Text Muted:   #6e7f96
```

---

## License

MIT — built for North Cyprus market by InexpoAI.
