# Authorized KKTCarabam Import

The user supplied a publication authorization dated 2026-10-29. The requested batch is 20-30 real vehicles with their actual galleries. No data or photographs should be fabricated.

## Current access result

Direct HTTPS requests from this environment are refused by Cloudflare ("Sorry, you have been blocked"). Public search text can be read, but does not expose the actual vehicle galleries. No real inventory has been imported. The existing KKT crawler no longer generates sample prices when requests fail.

To complete the import, obtain an authorized JSON export and image directory from the source, or have the source operator enable access for the importer. No challenge bypass or credential copying is needed.

## Export format

A JSON object containing `listings`, an array of up to 100 records:

- `external_id`: numeric source listing ID, supplied as a string
- `source_url`: HTTPS KKTCarabam listing URL matching that ID
- `brand`, `model`, `year`, `price`, `currency`, `city`
- Optional `mileage`, `fuel_type`, `transmission`
- `images`: relative filenames under the supplied image directory, with the complete gallery for that car

Prices must be numeric with the original GBP/EUR/USD/TRY currency; placeholder 0/1 prices are rejected. Images must be genuine JPG, PNG or WEBP files, at most 10 MB each. All images and records are validated before database writes. Image hashes deduplicate stored files. A unique source key prevents repeated imports even when an existing listing is soft deleted.

## Run

From `backend`:

```powershell
rtk php artisan migrate
rtk php artisan vehicles:import-authorized C:\exports\kktcarabam.json --images=C:\exports\images --owner=EXISTING_USER_UUID --dry-run
rtk php artisan vehicles:import-authorized C:\exports\kktcarabam.json --images=C:\exports\images --owner=EXISTING_USER_UUID
rtk php artisan storage:link
rtk php artisan queue:work --queue=default,ai --stop-when-empty
```

The command publishes imported listings locally, adds newly written factual English descriptions, source attribution and provenance metadata, and queues AI analysis after commit. Source prices are preserved. It never contacts a protected source or silently substitutes stock photos. This command does not deploy to aiprice.app; run it in the intended deployment database after review.
