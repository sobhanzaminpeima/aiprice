# Changelog

All notable changes to AIPrice.app are documented here.

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
