# Deployment Checklist: AIPrice.app

Release prepared on 2026-11-04. Use this list for the next production deploy
(Hostinger, `~/domains/aiprice.app/laravel_app`). Tick every item.

## 1. Before you deploy (owner only)

- [ ] **Change the hosting SSH/hPanel password** if it has ever been shared in chat or email, then switch to SSH key login.
- [ ] Rotate any password that appears in old local tooling files (for example `admin@hubprice.ai` test passwords). `.claude/settings.local.json` is no longer tracked in Git, but it still exists in Git history.
- [ ] Production `.env` filled from `scripts/deploy/production.env.example` (APP_KEY kept, `APP_DEBUG=false`, HTTPS URLs, MySQL credentials, SMTP password, `SESSION_SECURE_COOKIE=true`).
- [ ] `OPENAI_API_KEY` set if you want AI-written summary sentences. Without it, prices still work and summaries are rule-based.
- [ ] Stripe keys and webhook secret set only if paid plans are live.
- [ ] Optional: `VITE_MAP_TILE_URL` at build time, if you move off the public OpenStreetMap tile servers (needed once traffic grows; the OSM tile policy allows light use only).
- [ ] Database backup taken (the remote deploy script also backs up automatically).

## 2. Build and deploy

```bash
COMPOSER_BIN=composer bash scripts/deploy/build-release.sh
# upload build/aiprice-release-<sha>.tar.gz and scripts/deploy/remote-deploy.sh
bash remote-deploy.sh ~/domains/aiprice.app/laravel_app aiprice-release-<sha>.tar.gz
```

The script runs migrations. This release adds four migrations:

| Migration | Effect |
|---|---|
| `2026_11_04_000001_add_seller_contact_support` | `inquiries.share_contact`, new `listing_contact_reveals` table |
| `2026_11_04_000002_scrub_third_party_brand_names` | Removes "KKTC Arabam / kktcarabam.com" text and "AI-verified" wording from stored listings and Instagram captions |
| `2026_11_04_000003_remove_fabricated_competitor_rows` | Deletes invented `gen-*` competitor rows that were skewing prices |
| `2026_11_04_000004_move_imported_images_to_neutral_path` | Moves `storage/imports/kktcarabam/*` images to `storage/imports/vehicles/` and rewrites the URLs |
| `2026_11_05_000001_remove_demo_listings` | Deletes sample listings that use stock photos (images.unsplash.com); real listings stay |
| `2026_11_05_000002_backfill_listing_coordinates` | Places listings on the map whose town name was not recognised before |

## 3. Right after deploy

- [ ] `php artisan app:check-production` reports PASS for every row.
- [ ] Cron is set every minute: `/usr/bin/php .../artisan schedule:run-inline`.
- [ ] Admin → **AI Status** → **Re-score all active**. This is required once: the pricing model changed, and old scores were computed with the previous logic.
- [ ] Admin → AI Status shows "Jobs waiting" going down within a few minutes (this proves the cron/queue runs).
- [ ] Admin → AI Status → "Test the OpenAI connection" returns a reply (if a key is set).
- [ ] Admin → Requests inbox: send a test message from the contact form and check it appears with the full text.

## 4. Smoke test in the browser (production)

- [ ] Home loads in the **light** theme. The theme toggle switches to dark and back.
- [ ] Header menu: hover Cars, move to an option, click it, and the filtered list opens (for example `/cars?brand=Toyota`). Also try with the keyboard (Tab, Enter, Esc) and on a phone (tap).
- [ ] Listing detail as a guest: the phone shows `… 3•••`, and "Sign in to see" goes to the login page.
- [ ] Listing detail when signed in: "Show number" reveals the full number. "Send to seller" sends a message, and the seller sees it in Dashboard → Messages.
- [ ] Home → Browse by category tiles and price-class chips open filtered results.
- [ ] Register a new account; the verification email arrives.
- [ ] Admin: Users (suspend/reactivate), All Listings (create/edit/delete, image upload), Vehicle & Property catalog (add/delete a brand, model and property type).
- [ ] Map shows OpenStreetMap tiles with attribution (no "API key required" watermark).
- [ ] No listing page shows "kktcarabam", "KKTC Arabam" or "AI-verified".

## 5. Still needed from the owner

- Domain/DNS and SSL stay as they are today (aiprice.app on Hostinger). Confirm HTTPS is forced.
- Real seller phone numbers: sellers must add a phone in Dashboard → Profile, otherwise only "Send to seller" is shown.
- Decide whether to keep the competitor import (KKTCarabam) at all. Its data is used only inside price estimates and is never shown, but you need written permission from the source site.
- Legal pages (terms, privacy/KVKK/GDPR). Collecting phone numbers and recording number reveals needs a privacy notice.
