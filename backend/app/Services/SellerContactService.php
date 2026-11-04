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
        $phone = $listing->meta['contact_phone'] ?? $listing->user?->phone;
        $phone = is_string($phone) ? trim($phone) : '';

        return preg_match_all('/\d/', $phone) >= 6 ? $phone : null;
    }

    /** Public, privacy-safe seller summary for the listing detail page. */
    public function publicSummary(Listing $listing): array
    {
        $listing->loadMissing('user:id,name,phone,created_at');
        $phone = $this->phoneFor($listing);
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
