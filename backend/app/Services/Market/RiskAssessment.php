<?php

namespace App\Services\Market;

use App\Models\FraudReport;
use App\Models\Listing;

/**
 * A plain-language risk level for a listing, built only from things we can
 * measure: how far the asking price is from similar listings, the trust score,
 * how much data the price check had, and open fraud reports. It is guidance for
 * a buyer, never a verdict about the seller.
 */
class RiskAssessment
{
    /** @return array{level: string|null, note: string|null} */
    public function assess(Listing $listing, array $price, ?float $trustScore): array
    {
        $points = 0;
        $notes = [];

        $hasEstimate = (bool) ($price['has_estimate'] ?? false);
        $avg = (float) ($price['market_avg'] ?? 0);
        if ($hasEstimate && $avg > 0) {
            $deviation = ((float) $listing->price - $avg) / $avg * 100;
            if ($deviation < -30) {
                $points += 2;
                $notes[] = 'The price is far below similar listings. Check the documents and see the item in person before paying anything.';
            } elseif ($deviation > 30) {
                $points += 1;
                $notes[] = 'The price is well above similar listings, so there may be room to negotiate.';
            }
        } else {
            $points += 1;
            $notes[] = 'There are too few similar listings to judge the price yet.';
        }

        if ($trustScore !== null) {
            if ($trustScore < 50) {
                $points += 2;
                $notes[] = 'The listing has little verified information.';
            } elseif ($trustScore < 70) {
                $points += 1;
                $notes[] = 'Some details are missing from the listing.';
            }
        }

        $openReports = FraudReport::where('listing_id', $listing->id)->whereIn('status', ['pending', 'investigating', 'confirmed'])->count();
        if ($openReports > 0) {
            $points += 3;
            $notes[] = 'This listing has been reported and is being reviewed.';
        }

        if ($trustScore === null && ! $hasEstimate) {
            return ['level' => null, 'note' => null];
        }

        $level = $points >= 3 ? 'high' : ($points >= 1 ? 'medium' : 'low');
        $note = $notes ? implode(' ', array_slice($notes, 0, 2)) : 'The price is in line with similar listings and the listing is well documented.';

        return ['level' => $level, 'note' => mb_substr($note, 0, 500)];
    }
}
