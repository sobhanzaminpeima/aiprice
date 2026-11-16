<?php

namespace App\Services\Imports;

use App\Models\Imports\ImportSource;
use App\Models\Imports\SourceListing;

/**
 * The single place that decides what an imported listing may show publicly.
 * Each rule returns the reasons it fails, so admins see exactly why a listing
 * is held back.
 */
final class PublicationPolicy
{
    /** @return list<string> empty when the listing may be published */
    public function blockers(SourceListing $sl): array
    {
        $source = $sl->source;
        $reasons = [];
        if (! $source->listingDataAuthorized()) {
            $reasons[] = 'listing_data_permission_'.$source->listing_data_permission;
        }
        if (! $source->imagesAuthorized()) {
            $reasons[] = 'image_permission_'.$source->image_permission;
        }
        if (! $sl->listing_id || ! in_array($sl->status, ['imported', 'published'], true)) {
            $reasons[] = 'not_imported';
        }
        if (! $sl->source_active) {
            $reasons[] = 'no_longer_active_at_source';
        }
        if ($sl->images()->where('status', 'stored')->count() === 0) {
            $reasons[] = 'no_stored_images';
        }
        if ($source->permission_max_listings !== null) {
            $published = SourceListing::where('import_source_id', $source->id)->where('status', 'published')->where('id', '!=', $sl->id)->count();
            if ($published >= $source->permission_max_listings) {
                $reasons[] = 'permission_listing_limit_reached';
            }
        }

        return $reasons;
    }

    public function imagesMayBePublic(ImportSource $source): bool
    {
        return $source->imagesAuthorized();
    }

    /** A phone is public only with explicit contact permission and a number the source printed in full. */
    public function contactMayBePublic(SourceListing $sl): bool
    {
        return $sl->source->contactsAuthorized()
            && $sl->contact_publication_status === ImportSource::AUTHORIZED
            && $sl->seller_phone_e164 !== null;
    }
}
