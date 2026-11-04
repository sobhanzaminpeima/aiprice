# Changelog

All notable changes to AIPrice.app are documented here.

## 2026-11-04: Launch readiness

### Added
- "Send to seller / owner" on car and property pages; the seller gets an in-app and email notification. Buyer contact details are shared only when the buyer opts in.
- Seller phone privacy: guests see a masked number (last 3 digits hidden, enforced in the API). The full number comes from an authenticated, rate-limited endpoint, and every reveal is logged.
- bama.ir-style browsing: category rows (body type, quick filters, price classes, brands, property types, cities) on Home, Cars and Properties. A "Post an ad" button in the header.
- Working listing filters: brand, model, body type, fuel, transmission, year, mileage and property type (the backend previously ignored them, so menu links had no effect).
- Admin "AI Status" page: estimate coverage, queue health, market data sources, full re-score, and an OpenAI connection test.
- `BrandGuard`: every AI prompt and output and all imported text is kept on the AIPrice.app brand, with no third-party marketplace names or "verified/guaranteed price" claims.
- `docs/DEPLOYMENT_CHECKLIST.md`.

### Changed
- Light theme is now the default. Both themes use the brand palette (blue #159BF2, cyan #12C5E8, green #16C784, navy #081827), and light-mode text and accents meet WCAG AA contrast.
- AI price estimate rewritten: same-currency comparables only, similarity tiers (model/brand plus year, or property type, bedrooms and size), median with outlier trimming, competitor market data included, and "not enough data" instead of an invented verdict. Wording says "estimate", never "verified".
- Mega menu: hover gap bridge, 220 ms close delay, click/tap toggle, keyboard (focus, Esc) and ARIA support.
- Map tiles switched to OpenStreetMap with attribution (CARTO now watermarks keyless use).

### Removed
- Non-functional admin pages: Settings (nothing was saved), AI Intelligence, LLM Management (fake statistics; model registry unused) and AI Usage (logs never written).
- Crawler "sample data" fallback that inserted invented competitor prices.

### Security
- Fixed stored XSS in map popups (listing title, city and image were inserted as raw HTML).
- `listings.meta` and `import_source_key` are no longer serialized publicly.
- Global API rate limit (240/min per user or IP), and HSTS over HTTPS.
- `.claude/settings.local.json` (local paths and test credentials) removed from Git tracking.
- The sample-listing seeder refuses to run in production.

## 2026-09-26 — Production hardening and AIPrice rebrand

### Added

- AIPrice.app identity, refreshed logo assets, manifest, favicons, and application metadata
- Complete password reset, email verification, forced password change, auth throttling, and token revocation flows
- Endpoint-level staff permissions with full `super_admin` access and restricted specialist roles
- Queue-backed AI analysis, competitor crawling, notifications, announcements, and scheduled maintenance
- Real admin modules for finance, market analytics, live analytics, fraud, support, reports, content, and announcements
- Stripe-ready payment gateway abstraction, checkout, invoices, subscriptions, and signed webhook processing
- Public blog, FAQ, contact, market insights, investment center, and honest empty states
- Canonical metadata, Open Graph, JSON-LD, sitemap, crawler rules, and visit deduplication
- Shared-host-compatible release builder, database/file backup, maintenance mode, migrations, caching, and rollback

### Changed

- Replaced interface emoji with Lucide icons and hardened responsive navigation
- Limited the bottom navigation bar to mobile breakpoints
- Improved vehicle and property cards, listing details, maps, gallery behavior, filtering, CTA placement, and accessibility
- Replaced mock analytics and placeholder admin data with persisted application data

### Verified

- Frontend production build, TypeScript, ESLint, and Oxlint
- Backend test suite: 60 tests and 279 assertions
- Desktop and mobile smoke tests covering public pages, listing details, overflow, and console errors

### Release

- Production revision: `bef35f1`
