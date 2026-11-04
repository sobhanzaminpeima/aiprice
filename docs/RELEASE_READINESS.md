# Release Readiness Review

Reviewed locally on 2026-10-31 in `C:\Claude Projects\AiPrice`.

## Verified

- Fresh clone dependency installation and all 21 migrations on local SQLite.
- Backend: 68 tests passed, 326 assertions. Covers authentication, admin permissions, KYC, billing, notifications, public endpoints, production seeding safeguards, and vehicle/property seller-to-approval-to-buyer workflows.
- Frontend TypeScript check, ESLint without fixes, and production build passed.
- Production dependency audits: Composer `--locked --no-dev` and npm `--omit=dev` report no advisories after compatible dependency updates.
- Route caching succeeds.
- Local HTTP checks: frontend and backend health return 200, proxied listings API returns 200, unauthenticated admin API returns 401.

## Corrections

- Preserve Laravel runtime directory placeholders in Git so fresh clones can run Composer package discovery.
- Do not create the documented development administrator during production seeding.
- Provide an explicit test-only application key for isolated test runs.
- Release builder now requires frontend type checking and no longer suppresses PHP platform failures.
- Add `php artisan app:check-production`, a read-only configuration/database/build preflight command returning a nonzero status when requirements fail.
- Restrict agency updates to agency/dealer accounts and roles, synchronize Spatie roles, and revoke tokens upon suspension or role change. The agency route cannot change administrative accounts or grant staff access.
- Business verification no longer implicitly approves KYC identity documents.
- Public listing details no longer serialize ownership/insurance document records.
- Pending listings cannot be queried through alternatives or buyer inquiry endpoints.
- Public listing pagination validates page size between 1 and 100, including ranked searches.

## Deployment Gates Still Requiring the Hosting Environment

The local configuration is intentionally for development. It is not a production environment certification.

1. Configure production MySQL/MariaDB and run migrations after taking a backup. MySQL-specific behavior has not been exercised in this local SQLite run.
2. Set production environment, disable debug, configure HTTPS URLs, unique APP_KEY and secure cookies.
3. Configure and test real SMTP delivery and password/verification emails.
4. Configure AI credentials and verify an analysis job runs on the hosting queue worker/cron.
5. Configure live billing credentials and signed webhook delivery if paid subscriptions are enabled.
6. Copy the built SPA to Laravel public assets with its index named `spa.html` using the release process. Run `app:check-production` on the release host.
7. Provision an administrator with a private password; production seeding no longer creates a development administrator.
8. Verify backup restoration, document root, uploaded-file URLs and storage permissions.
9. Perform visual browser checks on mobile, tablet and desktop and end-to-end buyer/seller/admin flows on staging. HTTP checks are not visual verification.

There are 13 moderate development-tool advisories involving the ESLint/minimatch dependency chain. These remain for a compatible tooling upgrade; no forced ESLint downgrade was applied.

No production deploy, Git push or external listing import was performed during this review.

## Local Preview

- Frontend: http://127.0.0.1:3016
- Backend: http://127.0.0.1:8015

The local database contains reference data and the development admin, not imported vehicle inventory.
