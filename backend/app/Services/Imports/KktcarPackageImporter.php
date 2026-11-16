<?php

namespace App\Services\Imports;

use App\Models\Imports\ImportRun;
use App\Models\Imports\ImportSource;
use App\Models\Imports\SourceListing;
use App\Models\Listing;
use App\Models\PriceHistory;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Imports a prepared KKTCar export package (AIPRICE_KKTCAR_IMPORT, schema 1.0).
 *
 * Rules (from the package's CLAUDE_CODE_IMPORT.md and permissions report):
 *  - every record becomes a DRAFT listing; nothing is published or valued here;
 *  - no seller contacts and no images are stored (the gallery stays empty);
 *  - null stays null, currency is kept as given (no conversion);
 *  - records with data_flags are marked needs_review;
 *  - rights_status starts as clearance_required, which blocks publication.
 */
class KktcarPackageImporter
{
    public const SOURCE_KEY = 'kktcar';

    private const FUEL = ['Petrol' => 'petrol', 'Diesel' => 'diesel', 'Hybrid' => 'hybrid', 'Electric' => 'electric', 'LPG' => 'lpg', 'Plug-in Hybrid' => 'plug-in hybrid'];

    private const TRANSMISSION = ['Automatic' => 'automatic', 'Manual' => 'manual', 'Semi-automatic' => 'semi-automatic', 'CVT' => 'cvt'];

    private const BODY = ['Sedan' => 'sedan', 'Hatchback' => 'hatchback', 'Coupe' => 'coupe', 'SUV' => 'suv', 'Convertible' => 'convertible',
        'Estate' => 'estate', 'Station Wagon' => 'estate', 'MPV' => 'mpv', 'Minivan' => 'mpv', 'Pickup' => 'pickup', 'Van' => 'van', 'Crossover' => 'crossover'];

    private const CURRENCIES = ['GBP', 'TRY', 'USD', 'EUR'];

    private const REQUIRED = ['external_id', 'source_listing_id', 'source_slug', 'source_url', 'title', 'slug', 'brand', 'model', 'variant', 'year',
        'price', 'currency', 'price_negotiable', 'previous_price', 'price_change', 'open_to_trade', 'mileage', 'mileage_unit', 'mileage_source_value',
        'fuel_type', 'transmission', 'engine_capacity', 'body_type', 'color', 'steering', 'location', 'country', 'description', 'short_description',
        'features', 'seller', 'images', 'gallery', 'seo', 'quality_score', 'source_listed_date', 'source_checked_at', 'source_status', 'data_flags',
        'record_complete', 'status', 'rights_clearance_required'];

    public function __construct(private ListingImporter $listings) {}

    /**
     * Read and integrity-check the package directory.
     *
     * @return array{manifest: array, records: list<array>, checksum_ok: bool}
     */
    public function load(string $dir): array
    {
        $dir = rtrim($dir, '/\\');
        foreach (['manifest.json', 'vehicles.json'] as $f) {
            if (! is_file("$dir/$f")) {
                throw new InvalidArgumentException("Package file missing: $f");
            }
        }
        $raw = (string) file_get_contents("$dir/vehicles.json");
        if (! mb_check_encoding($raw, 'UTF-8')) {
            throw new InvalidArgumentException('vehicles.json is not valid UTF-8.');
        }
        $checksumOk = true;
        if (is_file("$dir/checksums.json")) {
            $sums = json_decode((string) file_get_contents("$dir/checksums.json"), true) ?: [];
            $checksumOk = isset($sums['vehicles.json']) && hash_equals($sums['vehicles.json'], hash('sha256', $raw));
        }
        if (! $checksumOk) {
            throw new InvalidArgumentException('vehicles.json does not match checksums.json.');
        }
        $json = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        $manifest = json_decode((string) file_get_contents("$dir/manifest.json"), true, 32, JSON_THROW_ON_ERROR);
        if (($json['schema_version'] ?? null) !== '1.0' || ($json['source'] ?? null) !== 'kktcar.com') {
            throw new InvalidArgumentException('Unsupported package: expected schema_version 1.0 from kktcar.com.');
        }

        return ['manifest' => $manifest, 'records' => array_values($json['vehicles'] ?? []), 'checksum_ok' => $checksumOk];
    }

    /** @return list<string> validation errors (empty = valid) */
    public function validate(array $r): array
    {
        $e = [];
        foreach (self::REQUIRED as $k) {
            if (! array_key_exists($k, $r)) {
                $e[] = "missing key: $k";
            }
        }
        if ($e) {
            return $e;
        }
        if (! preg_match('/^KKT_\d{4}$/', (string) $r['external_id'])) {
            $e[] = 'external_id format';
        }
        if (! Str::isUuid((string) $r['source_listing_id'])) {
            $e[] = 'source_listing_id is not a UUID';
        }
        if (! preg_match('#^https://kktcar\.com/(?:en/)?listing/[a-z0-9-]+$#', (string) $r['source_url']) || ! str_ends_with($r['source_url'], '/'.$r['source_slug'])) {
            $e[] = 'source_url invalid or does not match source_slug';
        }
        if (! is_int($r['year']) || $r['year'] < 1950 || $r['year'] > (int) date('Y') + 1) {
            $e[] = 'year not a plausible integer';
        }
        if (! is_int($r['price']) && ! is_float($r['price']) || $r['price'] <= 0) {
            $e[] = 'price not a positive number';
        }
        if (! in_array($r['currency'], self::CURRENCIES, true)) {
            $e[] = 'unsupported currency';
        }
        if ($r['mileage'] !== null && (! is_int($r['mileage']) || $r['mileage'] < 0)) {
            $e[] = 'mileage must be null or a non-negative integer';
        }
        if ($r['mileage_unit'] !== 'km') {
            $e[] = 'mileage_unit must be km';
        }
        foreach (['fuel_type' => self::FUEL, 'transmission' => self::TRANSMISSION, 'body_type' => self::BODY] as $f => $map) {
            if ($r[$f] !== null && ! isset($map[$r[$f]])) {
                $e[] = "unknown $f: {$r[$f]}";
            }
        }
        if (! is_string($r['brand']) || trim($r['brand']) === '' || ! is_string($r['model']) || trim($r['model']) === '') {
            $e[] = 'brand/model missing';
        }
        if (! is_string($r['location']) || trim($r['location']) === '') {
            $e[] = 'location missing';
        }
        if (! $this->isoDate($r['source_listed_date'], 'Y-m-d') || ! $this->isoDateTime($r['source_checked_at'])) {
            $e[] = 'dates are not ISO 8601';
        }
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $r['slug'])) {
            $e[] = 'slug format';
        }
        if ($r['status'] !== 'draft' || $r['rights_clearance_required'] !== true) {
            $e[] = 'record must be a draft requiring rights clearance';
        }
        if (! is_array($r['data_flags']) || ! is_array($r['images']) || ! is_array($r['seller'])) {
            $e[] = 'data_flags/images/seller must be arrays';
        }

        return $e;
    }

    /**
     * @return array{run: ImportRun, created: list<array>, skipped: list<array>, rejected: list<array>, flagged: list<array>, warnings: list<string>, reconciliation: array}
     */
    public function import(string $dir, bool $dryRun = false, ?User $by = null): array
    {
        ImportSource::ensureKnown();
        $source = ImportSource::where('key', self::SOURCE_KEY)->firstOrFail();
        $package = $this->load($dir);
        $records = $package['records'];

        $run = ImportRun::create([
            'import_source_id' => $source->id, 'triggered_by' => $by?->id, 'mode' => $dryRun ? 'dry_run' : 'import',
            'status' => 'running', 'target' => count($records), 'started_at' => now(),
        ]);
        $out = ['created' => [], 'skipped' => [], 'rejected' => [], 'flagged' => [], 'warnings' => []];
        if (($package['manifest']['record_count'] ?? null) !== count($records)) {
            $out['warnings'][] = 'manifest record_count '.($package['manifest']['record_count'] ?? 'missing').' ≠ '.count($records).' records in vehicles.json';
        }

        $seenFacts = [];
        foreach ($records as $r) {
            $id = $r['external_id'] ?? '?';
            $errors = $this->validate($r);
            if ($errors) {
                $out['rejected'][] = ['external_id' => $id, 'reasons' => $errors];

                continue;
            }
            // Never store contacts or images, even if a later package includes them.
            if (array_filter([$r['seller']['name'] ?? null, $r['seller']['phone'] ?? null, $r['seller']['whatsapp'] ?? null]) || $r['images']) {
                $out['warnings'][] = "$id: seller contact or images present in package — ignored, not stored";
            }

            $dup = $this->duplicateOf($source, $r, $seenFacts);
            if ($dup) {
                $out['skipped'][] = ['external_id' => $id, 'reason' => $dup];

                continue;
            }
            $seenFacts[$this->factsKey($r)] = $id;

            $flagged = $r['data_flags'] !== [];
            if ($flagged) {
                $out['flagged'][] = ['external_id' => $id, 'flags' => $r['data_flags']];
            }
            if ($dryRun) {
                $out['created'][] = ['external_id' => $id, 'title' => $r['title'], 'slug' => $this->uniqueSlug($r['slug']), 'dry_run' => true];

                continue;
            }
            try {
                $out['created'][] = $this->createDraft($source, $run, $r, $flagged, $by);
            } catch (Throwable $e) {
                $out['rejected'][] = ['external_id' => $id, 'reasons' => ['write failed: '.$e->getMessage()]];
            }
        }

        $out['reconciliation'] = $dryRun ? [] : $this->reconcile($records);
        $run->update([
            'status' => 'completed', 'finished_at' => now(),
            'candidates_discovered' => count($records), 'candidates_fetched' => count($records),
            'eligible' => count($records) - count($out['rejected']) - count($out['skipped']),
            'selected' => count($out['created']), 'imported' => $dryRun ? 0 : count($out['created']),
            'skipped_duplicates' => count($out['skipped']), 'rejected' => count($out['rejected']),
            'images_stored' => 0, 'contacts_pending' => $dryRun ? 0 : count($out['created']), 'errors_count' => count($out['reconciliation']['mismatches'] ?? []),
            'report' => array_diff_key($out, ['run' => 1]) + ['package' => $package['manifest']['package'] ?? null, 'checksum_ok' => $package['checksum_ok']],
        ]);
        $source->update(['last_run_at' => now()]);

        return ['run' => $run->fresh()] + $out;
    }

    /** Compare stored listings with the package: price, currency, year, mileage. */
    public function reconcile(array $records): array
    {
        $checked = 0;
        $mismatches = [];
        foreach ($records as $r) {
            $listing = Listing::withTrashed()->with('listable')->where('import_source_key', self::SOURCE_KEY.':'.($r['source_listing_id'] ?? ''))->first();
            if (! $listing) {
                continue;
            }
            $checked++;
            $pairs = [
                'price' => [(float) $listing->price, (float) $r['price']],
                'currency' => [$listing->currency, $r['currency']],
                'year' => [(int) $listing->listable?->year, $r['year']],
                'mileage' => [$listing->listable?->mileage, $r['mileage']],
            ];
            foreach ($pairs as $field => [$db, $json]) {
                if ($db !== $json) {
                    $mismatches[] = ['external_id' => $r['external_id'], 'field' => $field, 'db' => $db, 'json' => $json];
                }
            }
        }

        return ['checked' => $checked, 'mismatches' => $mismatches];
    }

    private function createDraft(ImportSource $source, ImportRun $run, array $r, bool $flagged, ?User $by): array
    {
        return DB::transaction(function () use ($source, $run, $r, $flagged, $by) {
            $brand = $this->listings->brand(trim($r['brand']));
            $model = VehicleModel::withTrashed()->where('brand_id', $brand->id)->whereRaw('LOWER(name) = ?', [Str::lower(trim($r['model']))])->first()
                ?? VehicleModel::create(['brand_id' => $brand->id, 'name' => trim($r['model']), 'is_active' => true]);

            $vehicle = Vehicle::create([
                'brand_id' => $brand->id, 'model_id' => $model->id, 'variant' => $r['variant'], 'year' => $r['year'],
                'mileage' => $r['mileage'],
                'fuel_type' => $r['fuel_type'] !== null ? self::FUEL[$r['fuel_type']] : null,
                'transmission' => $r['transmission'] !== null ? self::TRANSMISSION[$r['transmission']] : null,
                'body_type' => $r['body_type'] !== null ? self::BODY[$r['body_type']] : null,
                'color' => $r['color'], 'engine_size' => $r['engine_capacity'], // verbatim, e.g. "1.6 L"
                'condition' => 'used', 'features' => $r['features'] ?: null,
                'meta' => array_filter([
                    'open_to_trade' => $r['open_to_trade'], 'mileage_source_value' => $r['mileage_source_value'],
                    'mileage_unit' => $r['mileage_unit'], 'steering' => $r['steering'],
                ], fn ($v) => $v !== null),
            ]);

            $sl = SourceListing::create([
                'import_source_id' => $source->id, 'last_run_id' => $run->id,
                'source_listing_id' => $r['source_listing_id'], 'source_url' => $r['source_url'], 'canonical_url' => $r['source_url'],
                'status' => $flagged ? 'needs_review' : 'imported',
                'selection_score' => $r['quality_score'], 'completeness_score' => $r['record_complete'] ? 100 : null,
                'rejection_reasons' => $flagged ? $r['data_flags'] : null,
                'data' => array_diff_key($r, array_flip(['seller', 'images'])) + ['seller_type_label' => $r['seller']['type'] ?? null],
                'content_hash' => hash('sha256', json_encode($r)),
                'duplicate_fingerprint' => hash('sha256', $this->factsKey($r)),
                'source_published_at' => $r['source_listed_date'], 'source_checked_at' => Carbon::parse($r['source_checked_at']),
                'last_seen_at' => Carbon::parse($r['source_checked_at']), 'rights_status' => 'clearance_required',
                'source_active' => true, 'imported_at' => now(),
                'image_publication_status' => ImportSource::PENDING, 'contact_publication_status' => ImportSource::PENDING,
                'seller_type' => ($r['seller']['type'] ?? null) === 'Private seller' ? 'individual' : null,
            ]);

            $listing = Listing::create([
                'user_id' => $this->listings->systemOwner()->id,
                'type' => 'vehicle', 'listable_type' => Vehicle::class, 'listable_id' => $vehicle->id,
                'import_source_key' => self::SOURCE_KEY.':'.$r['source_listing_id'],
                'title' => $r['title'], 'slug' => $this->uniqueSlug($r['slug']), 'description' => $r['description'],
                'price' => $r['price'], 'currency' => $r['currency'],
                // Column is NOT NULL; false only means the UI makes no "negotiable" claim. The source value (null) stays in source_listings.data.
                'price_negotiable' => false,
                'city' => trim($r['location']), 'country' => 'CY',
                'images' => [], 'thumbnail' => null, 'status' => 'draft',
                'meta' => [
                    'import' => [
                        'source_key' => self::SOURCE_KEY, 'source_site' => 'kktcar.com', 'source_listing_id' => $r['source_listing_id'],
                        'source_url' => $r['source_url'], 'source_listing_record' => $sl->id, 'external_id' => $r['external_id'],
                        'package' => 'AIPRICE_KKTCAR_IMPORT', 'imported_at' => now()->toIso8601String(),
                        'source_published_at' => $r['source_listed_date'], 'source_checked_at' => $r['source_checked_at'],
                        'seller_claims_verified' => false, 'rights_status' => 'clearance_required',
                    ],
                    'seo' => ['meta_title' => $r['seo']['title'] ?? null, 'meta_description' => $r['seo']['description'] ?? null],
                    'review' => ['needs_manual_review' => $flagged, 'data_flags' => $r['data_flags']],
                ],
            ]);
            PriceHistory::create(['listing_id' => $listing->id, 'price' => $listing->price, 'currency' => $listing->currency,
                'changed_by' => 'import', 'reason' => 'Source asking price at import (KKTCar package)']);
            $sl->update(['listing_id' => $listing->id]);
            $this->listings->audit('import.package_listing_created', $listing, null, [
                'external_id' => $r['external_id'], 'status' => 'draft', 'needs_review' => $flagged, 'rights_status' => 'clearance_required',
            ], $by);

            return ['external_id' => $r['external_id'], 'listing_id' => $listing->id, 'slug' => $listing->slug, 'title' => $listing->title, 'needs_review' => $flagged];
        });
    }

    /** @return string|null reason when the record already exists */
    private function duplicateOf(ImportSource $source, array $r, array $seenFacts): ?string
    {
        if (SourceListing::where('import_source_id', $source->id)->where('source_listing_id', $r['source_listing_id'])->exists()
            || Listing::withTrashed()->where('import_source_key', self::SOURCE_KEY.':'.$r['source_listing_id'])->exists()) {
            return 'duplicate source_listing_id';
        }
        if (SourceListing::where('canonical_url', $r['source_url'])->exists()) {
            return 'duplicate source_url';
        }
        $key = $this->factsKey($r);
        if (isset($seenFacts[$key])) {
            return 'same vehicle facts as '.$seenFacts[$key].' in this package';
        }
        if ($r['mileage'] !== null) {
            $match = Listing::withTrashed()->where('type', 'vehicle')->where('price', $r['price'])->where('currency', $r['currency'])
                ->whereRaw('LOWER(city) = ?', [Str::lower(trim($r['location']))])
                ->whereHasMorph('listable', [Vehicle::class], fn ($q) => $q->where('year', $r['year'])->where('mileage', $r['mileage'])
                    ->whereHas('brand', fn ($b) => $b->whereRaw('LOWER(name) = ?', [Str::lower(trim($r['brand']))]))
                    ->whereHas('model', fn ($m) => $m->whereRaw('LOWER(name) = ?', [Str::lower(trim($r['model']))])))
                ->value('id');
            if ($match) {
                return "same brand/model/year/mileage/price/location as existing listing $match";
            }
        }

        return null;
    }

    private function factsKey(array $r): string
    {
        return implode('|', [Str::lower(trim((string) $r['brand'])), Str::lower(trim((string) $r['model'])), $r['year'], $r['mileage'] ?? 'null',
            $r['price'], $r['currency'], Str::lower(trim((string) $r['location']))]);
    }

    /** The package slug is a suggestion; append -2, -3… on collision (including soft-deleted rows). */
    private function uniqueSlug(string $slug): string
    {
        $candidate = $slug;
        for ($i = 2; Listing::withTrashed()->where('slug', $candidate)->exists(); $i++) {
            $candidate = "$slug-$i";
        }

        return $candidate;
    }

    private function isoDate(mixed $v, string $format): bool
    {
        if (! is_string($v)) {
            return false;
        }
        $d = \DateTime::createFromFormat('!'.$format, $v);

        return $d !== false && $d->format($format) === $v;
    }

    private function isoDateTime(mixed $v): bool
    {
        return is_string($v) && (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $v) && strtotime($v) !== false;
    }
}
