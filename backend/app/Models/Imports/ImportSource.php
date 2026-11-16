<?php

namespace App\Models\Imports;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A third-party listing source and the permission scope an admin has recorded
 * for it. Each permission is one of PERMISSION_STATES; only "authorized"
 * allows the corresponding publication.
 */
class ImportSource extends Model
{
    use HasUuids;

    public const AUTHORIZED = 'authorized';
    public const PENDING = 'pending_authorization';
    public const DENIED = 'denied';
    public const PERMISSION_STATES = [self::AUTHORIZED, self::PENDING, self::DENIED];

    protected $fillable = [
        'key', 'name', 'base_url', 'status',
        'listing_data_permission', 'image_permission', 'contact_permission',
        'attribution_required', 'permission_reference', 'permission_granted_at',
        'permission_max_listings', 'permission_recorded_by', 'request_delay_ms', 'notes', 'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'attribution_required' => 'boolean',
            'permission_granted_at' => 'date',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * Known sources. Permissions start as pending: an admin must record what
     * the source actually authorized before anything is published.
     */
    public static function ensureKnown(): void
    {
        self::firstOrCreate(['key' => 'kktcarabam'], [
            'name' => 'KKTCarabam', 'base_url' => 'https://www.kktcarabam.com', 'status' => 'active',
            'attribution_required' => true, 'request_delay_ms' => 2500,
            'notes' => 'Public pages only. Seller phone numbers are masked behind a click-to-reveal on the source and are never extracted. '
                .'Source member terms prohibit copying platform content without permission.',
        ]);
        self::firstOrCreate(['key' => 'kktcar'], [
            'name' => 'KKTCar', 'base_url' => 'https://kktcar.com', 'status' => 'active',
            'attribution_required' => true, 'request_delay_ms' => 2500,
            'notes' => 'Imported from a prepared export package (AIPRICE_KKTCAR_IMPORT). Not cleared for commercial republication: '
                .'no written permission from KKTCar or advertisers; no images or seller contacts exported.',
        ]);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ImportRun::class);
    }

    public function sourceListings(): HasMany
    {
        return $this->hasMany(SourceListing::class);
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function listingDataAuthorized(): bool
    {
        return $this->listing_data_permission === self::AUTHORIZED;
    }

    public function imagesAuthorized(): bool
    {
        return $this->image_permission === self::AUTHORIZED;
    }

    public function contactsAuthorized(): bool
    {
        return $this->contact_permission === self::AUTHORIZED;
    }

    public function displayHost(): string
    {
        return (string) preg_replace('/^www\./', '', (string) parse_url($this->base_url, PHP_URL_HOST));
    }
}
