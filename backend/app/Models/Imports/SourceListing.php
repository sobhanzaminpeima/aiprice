<?php

namespace App\Models\Imports;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Provenance and normalized snapshot of one listing on a source website.
 * Seller contact fields and the original description are internal: they are
 * hidden from serialization and only surfaced through the admin API.
 */
class SourceListing extends Model
{
    use HasUuids;

    protected $fillable = [
        'import_source_id', 'last_run_id', 'listing_id', 'source_listing_id', 'source_url', 'canonical_url',
        'status', 'selection_score', 'completeness_score', 'extraction_confidence', 'score_breakdown',
        'rejection_reasons', 'data', 'source_description', 'content_hash', 'duplicate_fingerprint',
        'source_published_at', 'last_seen_at', 'source_active', 'imported_at',
        'image_publication_status', 'contact_publication_status',
        'seller_type', 'seller_display_name', 'seller_phone', 'seller_phone_e164', 'attempts', 'last_error',
    ];

    protected $hidden = ['seller_phone', 'seller_phone_e164', 'seller_display_name', 'source_description'];

    protected function casts(): array
    {
        return [
            'selection_score' => 'float',
            'score_breakdown' => 'array',
            'rejection_reasons' => 'array',
            'data' => 'array',
            'source_published_at' => 'date',
            'last_seen_at' => 'datetime',
            'imported_at' => 'datetime',
            'source_active' => 'boolean',
            'seller_phone' => 'encrypted',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ImportSource::class, 'import_source_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    public function images(): HasMany
    {
        return $this->hasMany(SourceListingImage::class)->orderBy('position');
    }

    public static function importKey(string $sourceKey, string $sourceListingId): string
    {
        return $sourceKey.':'.$sourceListingId;
    }
}
