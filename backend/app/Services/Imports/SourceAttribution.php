<?php

namespace App\Services\Imports;

use App\Models\Imports\ImportSource;
use App\Models\Listing;

/**
 * Public provenance block for imported listings: where the advert came from,
 * a link to the original when the source requires attribution, and a clear
 * note that seller information is unverified.
 */
final class SourceAttribution
{
    public static function for(Listing $listing): ?array
    {
        $import = $listing->meta['import'] ?? null;
        if (! $listing->import_source_key || ! is_array($import)) {
            return null;
        }
        $source = ImportSource::where('key', $import['source_key'] ?? '')->first();
        $attribute = $source?->attribution_required ?? true;

        return [
            'imported' => true,
            'source_name' => $attribute ? ($import['source_site'] ?? null) : null,
            'original_url' => $attribute ? ($import['source_url'] ?? null) : null,
            'source_published_at' => $import['source_published_at'] ?? null,
            'imported_at' => $import['imported_at'] ?? null,
            'seller_claims_verified' => false,
            'note' => ContentBuilder::UNVERIFIED_NOTE,
        ];
    }
}
