# AIPrice.app — Developer Setup Guide

## Architecture

```
Frontend (Vue 3 + Vite)  →  localhost:3000
     ↕ proxy /api → localhost:8000
Backend (Laravel 12)      →  localhost:8000
     ↕
MySQL (XAMPP)             →  localhost:3306  DB: aiprice
```

## Stack

| Layer    | Technology                                      |
|----------|-------------------------------------------------|
| Frontend | Vue 3 + TypeScript + Pinia + TailwindCSS v4     |
| Backend  | Laravel 12 / PHP 8.2 / Sanctum + Spatie Perms  |
| Database | MySQL 8 (via XAMPP)                             |
| Auth     | Laravel Sanctum (Bearer tokens)                 |

---

## Starting the System

### 1. Start MySQL (XAMPP)
```bat
C:\xampp\mysql_start.bat
```
Or use XAMPP Control Panel → Start MySQL.

### 2. Start Laravel Backend
```powershell
cd "C:\Claude Projects\Hubprice\backend"
php artisan serve --host=127.0.0.1 --port=8000
```
If `artisan serve` reports `Failed to listen`, use PHP's built-in server directly:
`php -S 127.0.0.1:8000 -t public public/index.php`

### 3. Start the queue worker and scheduler
AI scoring, competitor crawls and all emails/notifications run on the queue —
without a worker they sit in the `jobs` table and nothing is sent.
```powershell
cd "C:\Claude Projects\Hubprice\backend"
php artisan queue:work --queue=default,ai,crawl
```
In a second terminal (runs expiry, re-scoring and crawl tasks on schedule):
```powershell
php artisan schedule:work
```
In production, run the worker under a supervisor (systemd/Supervisor) and add
one cron entry: `* * * * * php /path/to/backend/artisan schedule:run`.

On Hostinger shared hosting (aiprice.app) `proc_open`/`exec` are disabled, so
`schedule:run` can't launch anything. Instead set `QUEUE_WORKER_VIA_SCHEDULER=true`
and use one cron entry that runs everything in-process:
`php /home/u188457652/domains/aiprice.app/laravel_app/artisan schedule:run-inline`

### Deploying
```bash
COMPOSER_BIN=composer bash scripts/deploy/build-release.sh
```
Then upload `build/aiprice-release-<sha>.tar.gz` and `scripts/deploy/remote-deploy.sh`
to the server and run `bash remote-deploy.sh ~/domains/aiprice.app/laravel_app <tarball>`.
It backs up the database and files to `~/aiprice-backups`, and restores the
previous release automatically if any step fails.

### 4. Start Vue Frontend
```powershell
cd "C:\Claude Projects\Hubprice\frontend"
npm run dev
```

Then open: **http://localhost:3000**

### Email
`MAIL_MAILER=log` writes every email to `storage/logs/laravel.log` — that is
where to find password-reset and verification links in development. Set SMTP
credentials (or Postmark/SES/Resend) in `.env` for real delivery, and set
`FRONTEND_URL` so links in emails point at the SPA.

### Tests
```powershell
cd "C:\Claude Projects\Hubprice\backend"
php vendor/bin/phpunit
```

---

## Admin Login

- URL: `http://localhost:3000/admin`
- Email: `admin@aiprice.app`
- Password: `Admin@123456`

---

## API Architecture

### Base URL
Frontend uses Vite proxy — all `/api` calls are forwarded to Laravel:

```typescript
// frontend/src/api/client.ts
const client = axios.create({ baseURL: '/api/v1' })
// /api/v1/listings → http://localhost:8000/api/v1/listings
```

### Authentication Flow
1. POST `/api/v1/auth/login` → returns `{ user, token }`
2. Token stored in `localStorage` as `hp_token`
3. All requests: `Authorization: Bearer {token}`

### Key API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/login` | Login |
| POST | `/api/v1/auth/register` | Register |
| GET | `/api/v1/auth/me` | Current user |
| GET | `/api/v1/listings` | Public listings |
| GET | `/api/v1/listings/{id}` | Listing detail |
| GET | `/api/v1/admin/dashboard` | Admin stats |
| GET | `/api/v1/admin/users` | User management |
| GET | `/api/v1/admin/listings` | Listing management |
| GET | `/api/v1/admin/vehicle-brands` | Vehicle brands |
| GET | `/api/v1/admin/competitor-sources` | Competitor data |

---

## Database

### Connection (backend/.env)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aiprice
DB_USERNAME=root
DB_PASSWORD=
```

### Key Tables
- `users` — UUID PK, roles via Spatie
- `listings` — polymorphic (vehicle/property/rental)
- `vehicles`, `properties` — listable types
- `competitor_listings` — AI-only data, never shown publicly
- `personal_access_tokens` — Sanctum tokens

### Migrations
```bash
cd backend
php artisan migrate          # Run migrations
php artisan migrate:rollback # Rollback last batch
php artisan db:seed          # Seed sample data
```

---

## Frontend Structure

```
frontend/src/
├── api/client.ts         # Axios instance with auth interceptor
├── stores/auth.ts        # Pinia auth store
├── router/index.ts       # Vue Router with admin guards
├── layouts/
│   ├── MainLayout.vue    # Public layout
│   └── AdminLayout.vue   # Admin sidebar layout
├── views/
│   ├── admin/            # 18 admin section views
│   ├── dashboard/        # User dashboard
│   └── marketplace/      # Cars/Properties/Rentals
└── components/
    ├── PriceBattery.vue  # AI price score component
    └── listings/         # Listing cards
```

### Design System (Dark Theme)
```css
--bg-base:      #05070b
--bg-card:      #0d1117
--teal:         #6fffd2
--cyan:         #56d8ff
--violet:       #a78bfa
--text-primary: #f0f6fc
--text-muted:   #6e7f96
```

**CRITICAL:** No `@apply` in Vue `<style scoped>` — use inline `style=` with hex values.

---

## Adding New Features

### New Admin Section
1. Create `frontend/src/views/admin/AdminXxxView.vue`
2. Add route in `frontend/src/router/index.ts`
3. Add link in `frontend/src/layouts/AdminLayout.vue`
4. Create Laravel controller: `php artisan make:controller Admin/XxxController`
5. Add route in `backend/routes/api.php` inside admin middleware group

### New API Endpoint
```php
// backend/routes/api.php
Route::middleware(['auth:sanctum', 'role:admin|super_admin'])->prefix('admin')->group(function () {
    Route::get('/my-endpoint', [Admin\MyController::class, 'index']);
});
```

### New Migration
```bash
php artisan make:migration add_field_to_table
php artisan migrate
```

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| 401 on admin routes | User must have `admin` or `super_admin` role |
| MySQL not connecting | Start XAMPP MySQL or run `C:\xampp\mysql_start.bat` |
| BOM in JSON response | Check PHP files for UTF-8 BOM; remove with `node -e "..."` |
| Vite proxy not working | Ensure Laravel is running on port 8000 |
| `@apply` error in Vue | Move to global CSS or use inline `style=` attribute |
| Token null after login | Check for BOM in `routes/api.php` or controller files |

---

## User Roles (Spatie Permissions)

```
super_admin → full access
admin       → admin dashboard
moderator   → listing moderation
dealer      → premium listings
agency      → bulk listings
seller      → individual listings
buyer       → browse + inquire
```

Check role: `php artisan tinker --execute="echo App\Models\User::find('uuid')->role;"`
