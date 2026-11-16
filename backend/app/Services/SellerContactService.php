<?php

namespace App\Services;

use App\Models\Listing;

/**
 * What a buyer may see about a listing's seller.
 *
 * The full phone number is never part of the public listing payload — guests
 * get a masked number only, and the full number is returned by a separate,
 * authenticated and rate-limited endpoint (GET /listings/{id}/seller-phone).
 * Hiding digits in CSS alone would leak them in the JSON response.
 */
class SellerContactService
{
    /** Number of trailing digits hidden from guests. */
    public const HIDDEN_DIGITS = 3;

    public function phoneFor(Listing $listing): ?string
    {
        $listing->loadMissing('user:id,name,phone,created_at');
        // Imported listings belong to a system account: only a contact number
        // explicitly authorized for republication may be shown, never the owner's.
        $phone = $listing->import_source_key
            ? ($listing->meta['contact_phone'] ?? null)
            : ($listing->meta['contact_phone'] ?? $listing->user?->phone);
        $phone = is_string($phone) ? trim($phone) : '';

        return preg_match_all('/\d/', $phone) >= 6 ? $phone : null;
    }

    /** Public, privacy-safe seller summary for the listing detail page. */
    public function publicSummary(Listing $listing): array
    {
        $listing->loadMissing('user:id,name,phone,created_at');
        $phone = $this->phoneFor($listing);
        if ($listing->import_source_key) {
            return $this->importedSummary($listing, $phone);
        }
        $name = trim((string) $listing->user?->name);

        return [
            // First name only; the full identity stays private.
            'display_name' => $this->firstName($name),
            'member_since' => $listing->user?->created_at?->format('Y'),
            'has_phone' => $phone !== null,
            'phone_masked' => $phone ? self::mask($phone) : null,
            'can_message' => true,
        ];
    }

    /**
     * Imported listing: the seller is not an AIPrice member, so in-app
     * messages would reach nobody. The seller name is shown only when its
     * republication is authorized; buyers are pointed to the original listing.
     */
    private function importedSummary(Listing $listing, ?string $phone): array
    {
        $record = isset($listing->meta['import']['source_listing_record'])
            ? \App\Models\Imports\SourceListing::find($listing->meta['import']['source_listing_record'])
            : null;
        $authorized = $record && $record->contact_publication_status === \App\Models\Imports\ImportSource::AUTHORIZED;

        return [
            'display_name' => $authorized && $record->seller_display_name ? $record->seller_display_name : 'Seller (imported listing)',
            'member_since' => null,
            'has_phone' => $phone !== null,
            'phone_masked' => $phone ? self::mask($phone) : null,
            'can_message' => false,
            'imported' => true,
        ];
    }

    private function firstName(string $name): string
    {
        foreach (preg_split('/\s+/u', $name) ?: [] as $part) {
            if ($part !== '' && ! preg_match('/^(mr|mrs|ms|miss|dr|sir|bay|bayan)\.?$/iu', $part)) {
                return $part;
            }
        }

        return 'Private seller';
    }

    /** "+90 533 812 3456" → "+90 533 812 3•••" (keeps formatting, hides the last digits). */
    public static function mask(string $phone, int $hidden = self::HIDDEN_DIGITS): string
    {
        $chars = preg_split('//u', $phone, -1, PREG_SPLIT_NO_EMPTY);
        for ($i = count($chars) - 1; $i >= 0 && $hidden > 0; $i--) {
            if (ctype_digit($chars[$i])) {
                $chars[$i] = '•';
                $hidden--;
            }
        }

        return implode('', $chars);
    }
}
