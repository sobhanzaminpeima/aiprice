# Vehicle listing imports (KKTCarabam)

## Status (2026-11-16)

- **Blocked at the source.** The importer's HTTP client receives a Cloudflare managed challenge ("Just a moment…", HTTP 403) on listing and category pages. The importer treats this as an access restriction and stops. It does not switch clients, impersonate a browser, or solve challenges. The formal dry run (target 35) therefore discovered **0** candidates and wrote no listings, media or contacts.
- **Permission scope is not on file.** A publication authorization dated 2026-10-29 was reported, but its text and scope are not in the repository. The source's member terms prohibit copying platform content without permission. All three permissions for the source therefore start as `pending_authorization`.
- **Seller phones are masked** on the source behind a click-to-reveal call. The importer never calls the reveal and never stores masked or partial numbers.

### To unblock (any one)

1. The source operator allowlists the importer's user agent `AIPriceListingImporter/1.0` (for example a Cloudflare WAF skip rule). The live adapter then works unchanged.
2. The operator supplies an authorized export (JSON + image directory). Run it through the same pipeline with `--feed`; see below.

Then record the permission scope in **Admin → Listing imports → Record permission**. You must enter a reference to the written permission before anything can be marked authorized.

## Pipeline

`discover → fetch (robots.txt checked, 2.5 s between requests, honest UA) → parse → normalize → score → de-duplicate → select (20–50, target 35) → [import] private media + draft listings → [approve] publish`

| Stage | Behaviour |
|---|---|
| Normalization | Unknown values are stored as `null`, never guessed. Prices keep their original currency. The GBP equivalent is derived only from the TL equivalents the source prints. |
| Scoring (0–100) | Completeness 30, photos 20, price clarity 15, identity 15, freshness 10, description 5, authorized contact 5. A low price never raises the score. |
| Hard rejections | Missing or implausible price, missing make/model/year/mileage, new vehicle, non-car category, fewer than 4 photos, title/spec/description conflicts, duplicate vehicle. |
| Selection | Deterministic and greedy by score, with caps per make, per model and per GBP price band. It never pads the result: fewer eligible listings means fewer selected. |
| Media | Saved to the **private** disk with SHA-256 de-duplication, plus a WebP (≤1280 px) and a thumbnail (480 px) that keep the aspect ratio. Gallery order and source URLs are preserved, and watermarks are left alone. Files are copied to the public disk only on approval when the photo permission is authorized. |
| Listings | Always created as `draft`, owned by an inactive system account. The text is original and factual and includes an "unverified seller information" note. The seller's description stays internal. |
| Contacts | A private individual's name and any phone are stored only under contact permission, and only if the page printed the number in full. Public payloads never fall back to the owner account's phone. |
| Valuation | Queued (`AnalyzeListingPrice`, `ai` queue) on publish, or on demand from the dashboard. It is separate from ingestion and shows "insufficient data" when there are fewer than 3 comparables. |
| Attribution | When `attribution_required` is set (the default), the public page shows the source name, a link to the original advert, and the unverified-information note. |

**Update policy.** Each source listing maps to one AIPrice listing (`listings.import_source_key = kktcarabam:<id>`, checked including soft-deleted rows, plus a unique `(source, source_listing_id)` and a unique canonical URL). Re-runs never overwrite an AIPrice listing. A recheck records price or mileage changes in `source_listings.data.source_changes` for review, and unpublishes listings that are gone at the source.

## Commands (from `backend`)

```powershell
php artisan migrate
php artisan imports:run kktcarabam --dry-run --target=35 --report=storage/app/private/import-reports/dry-run.json
php artisan imports:run kktcarabam --from-run=<DRY_RUN_ID>          # re-confirm selection, store media, create drafts
php artisan imports:run kktcarabam --feed=C:\exports\kkt.json --images=C:\exports\images --dry-run
php artisan imports:run kktcarabam --publish-approved                # only rows passing PublicationPolicy
php artisan queue:work --queue=default,ai,crawl
```

Nothing is scheduled. A recurring import needs explicit approval.

## Admin API (`/api/v1/admin/imports`, permission `manage_imports`)

`GET /` overview · `PATCH /sources/{id}` permissions, pause/resume · `POST /sources/{id}/runs` dry run / import · `GET /runs`, `/runs/{id}` · `GET /listings?status=&run=` · `GET /listings/{id}` (with signed private image previews) · `POST /listings/{id}/approve|unpublish|remove|recheck|analyze` · `PATCH /listings/{id}/contact` (correction/removal, audited without storing values in the log).

## Legacy

`vehicles:import-authorized` (the older JSON command) publishes immediately and supports AI-edited images. It predates these rules and should not be used for new batches. The existing 2012 Volkswagen Passat (source 269704) was imported that way with watermark-removed images and should be reviewed against the current watermark policy.
