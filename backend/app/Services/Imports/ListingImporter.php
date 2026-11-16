<?php

namespace App\Services\Imports;

use App\Jobs\AnalyzeListingPrice;
use App\Models\AuditLog;
use App\Models\Imports\ImportSource;
use App\Models\Imports\SourceListing;
use App\Models\Listing;
use App\Models\PriceHistory;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Writes imported vehicles into the existing listings/vehicles schema.
 *
 * UPDATE POLICY (documented, deliberate):
 *  - A listing is created once per source listing (unique import_source_key
 *    'source:id', checked including soft-deleted rows). Re-running an import
 *    never overwrites an existing AIPrice listing; it only refreshes the
 *    source snapshot (source_listings.data / last_seen_at) and records a
 *    source price change for admin review.
 *  - New listings are always created as 'draft'. publish() is the only path
 *    to 'active' and it enforces PublicationPolicy.
 */
class ListingImporter
{
    public const SYSTEM_OWNER_EMAIL = 'imports@system.aiprice.invalid';

    public function __construct(
        private ContentBuilder $content,
        private PublicationPolicy $policy,
        private ImageImporter $images,
    ) {}

    /** @return array{created: bool, listing: Listing} */
    public function createDraft(SourceListing $sl): array
    {
        $key = SourceListing::importKey($sl->source->key, $sl->source_listing_id);

        return DB::transaction(function () use ($sl, $key) {
            $existing = Listing::withTrashed()->where('import_source_key', $key)->lockForUpdate()->first();
            if ($existing) {
                $sl->update(['listing_id' => $existing->id]);

                return ['created' => false, 'listing' => $existing];
            }

            $d = $sl->data;
            $brand = $this->brand($d['make']);
            $model = VehicleModel::withTrashed()->where('brand_id', $brand->id)->whereRaw('LOWER(name) = ?', [Str::lower($d['model'])])->first()
                ?? VehicleModel::create(['brand_id' => $brand->id, 'name' => $d['model'], 'is_active' => true, 'body_type' => $d['body_type']]);

            $vehicle = Vehicle::create([
                'brand_id' => $brand->id, 'model_id' => $model->id, 'variant' => $d['variant'] ?? null,
                'year' => $d['model_year'], 'mileage' => $d['mileage'],
                'fuel_type' => ContentBuilder::vehicleFuel($d['fuel_type']),
                'transmission' => ContentBuilder::vehicleTransmission($d['transmission']),
                'body_type' => $d['body_type'], 'color' => $d['exterior_color'],
                'engine_size' => $d['engine_cc'] ? (string) $d['engine_cc'] : null, 'horsepower' => $d['horsepower'],
                'condition' => 'used', 'features' => $d['features'] ?: null,
                'meta' => array_filter([
                    'steering' => $d['steering'], 'drivetrain' => $d['drivetrain'], 'doors' => $d['doors'], 'seats' => $d['seats'],
                    'registration_status' => $d['registration_status'], 'mileage_unit' => $d['mileage_unit'],
                ], fn ($v) => $v !== null),
            ]);

            $listing = Listing::create([
                'user_id' => $this->systemOwner()->id,
                'type' => 'vehicle', 'listable_type' => Vehicle::class, 'listable_id' => $vehicle->id,
                'import_source_key' => $key,
                'title' => $this->content->title($d),
                'description' => $this->content->description($d),
                'price' => $d['asking_price'], 'currency' => $d['currency'],
                'city' => $d['city'], 'district' => $d['district'], 'country' => 'CY',
                'images' => [], 'thumbnail' => null,
                'status' => 'draft',
                'meta' => [
                    'import' => [
                        'source_key' => $sl->source->key,
                        'source_site' => $sl->source->displayHost(),
                        'source_listing_id' => $sl->source_listing_id,
                        'source_url' => $sl->canonical_url,
                        'source_listing_record' => $sl->id,
                        'imported_at' => now()->toIso8601String(),
                        'source_published_at' => $d['source_published_at'],
                        'seller_claims_verified' => false,
                    ],
                    'seo' => $this->content->seo($d),
                ],
            ]);
            PriceHistory::create(['listing_id' => $listing->id, 'price' => $listing->price, 'currency' => $listing->currency,
                'changed_by' => 'import', 'reason' => 'Source asking price at import']);

            $sl->update(['listing_id' => $listing->id, 'status' => 'imported', 'imported_at' => now()]);
            $this->audit('import.listing_created', $listing, null, ['source_listing' => $sl->id, 'status' => 'draft']);

            return ['created' => true, 'listing' => $listing];
        });
    }

    /** Attach public images (only when authorized), then activate. */
    public function publish(SourceListing $sl, ?User $by = null): Listing
    {
        $blockers = $this->policy->blockers($sl);
        if ($blockers) {
            throw new RuntimeException('Cannot publish: '.implode(', ', $blockers));
        }
        $public = $this->images->publish($sl);
        $sl->update(['image_publication_status' => ImportSource::AUTHORIZED]);

        return DB::transaction(function () use ($sl, $public, $by) {
            $listing = Listing::withTrashed()->lockForUpdate()->findOrFail($sl->listing_id);
            $old = $listing->only(['status', 'published_at']);
            $listing->update([
                'images' => array_column($public, 'full'),
                'thumbnail' => $public[0]['thumb'] ?? null,
                'status' => 'active',
                'published_at' => $listing->published_at ?? now(),
                'meta' => $this->withContactMeta($listing, $sl),
            ]);
            if ($listing->trashed()) {
                $listing->restore();
            }
            $sl->update(['status' => 'published']);
            $this->audit('import.listing_published', $listing, $old, $listing->only(['status', 'published_at']), $by);
            AnalyzeListingPrice::dispatch($listing->id)->afterCommit();

            return $listing;
        });
    }

    public function unpublish(SourceListing $sl, string $reason, ?User $by = null, string $status = 'imported'): ?Listing
    {
        return DB::transaction(function () use ($sl, $reason, $by, $status) {
            $listing = $sl->listing_id ? Listing::withTrashed()->lockForUpdate()->find($sl->listing_id) : null;
            if ($listing) {
                $old = $listing->only(['status']);
                $listing->update(['status' => 'draft', 'images' => [], 'thumbnail' => null]);
                $this->audit('import.listing_unpublished', $listing, $old, ['status' => 'draft', 'reason' => $reason], $by);
            }
            $this->images->unpublish($sl);
            $sl->update(['status' => $status]);

            return $listing;
        });
    }

    /** Removal request (seller/source/rights holder): archive + delete public media + clear contact. */
    public function remove(SourceListing $sl, string $reason, ?User $by = null): void
    {
        DB::transaction(function () use ($sl, $reason, $by) {
            $this->unpublish($sl, $reason, $by, 'removed');
            if ($sl->listing_id && ($listing = Listing::withTrashed()->find($sl->listing_id))) {
                $listing->update(['status' => 'archived']);
                $listing->delete();
                $this->audit('import.listing_removed', $listing, null, ['reason' => $reason], $by);
            }
            $sl->update([
                'seller_display_name' => null, 'seller_phone' => null, 'seller_phone_e164' => null,
                'contact_publication_status' => ImportSource::DENIED, 'image_publication_status' => ImportSource::DENIED,
            ]);
        });
    }

    /** Re-run valuation separately from ingestion. */
    public function queueValuation(SourceListing $sl): bool
    {
        if (! $sl->listing_id) {
            return false;
        }
        AnalyzeListingPrice::dispatch($sl->listing_id);

        return true;
    }

    public function systemOwner(): User
    {
        return User::firstOrCreate(['email' => self::SYSTEM_OWNER_EMAIL], [
            'name' => 'AIPrice imported listings',
            // Unusable password: the account exists only to own imported rows.
            'password' => Hash::make(Str::random(64)),
            'is_active' => false,
            'role' => 'seller',
        ]);
    }

    public function audit(string $action, Listing $listing, ?array $old, ?array $new, ?User $by = null): void
    {
        AuditLog::create([
            'user_id' => $by?->id ?? auth()->id(),
            'action' => $action, 'model_type' => Listing::class, 'model_id' => $listing->id,
            'old_values' => $old, 'new_values' => $new,
            'ip_address' => request()?->ip(), 'user_agent' => mb_substr((string) request()?->userAgent(), 0, 255),
        ]);
    }

    private function withContactMeta(Listing $listing, SourceListing $sl): array
    {
        $meta = $listing->meta ?? [];
        unset($meta['contact_phone']);
        if ($this->policy->contactMayBePublic($sl)) {
            $meta['contact_phone'] = $sl->seller_phone_e164;
        }

        return $meta;
    }

    private function brand(string $name): VehicleBrand
    {
        $norm = fn (string $s) => preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($s)));
        foreach (VehicleBrand::withTrashed()->get(['id', 'name', 'deleted_at']) as $b) {
            if ($norm($b->name) === $norm($name)) {
                if ($b->trashed()) {
                    $b->restore();
                }

                return $b;
            }
        }

        return VehicleBrand::create(['name' => $name, 'is_active' => true]);
    }
}
