<?php

namespace App\Services;

use App\Models\FraudReport;
use App\Models\Listing;

/**
 * Rule-based risk signals for a listing. Deliberately simple and explainable:
 * each signal has a weight and a human-readable reason, so a reviewer can see
 * exactly why something was flagged instead of trusting an opaque score.
 */
class FraudSignals
{
    /** @return array{score:int, signals:array<int,array{key:string,weight:int,reason:string}>} */
    public function assess(Listing $listing): array
    {
        $listing->loadMissing('user');
        $seller = $listing->user;
        $signals = [];

        // The classic scam: a desirable item far below market to lure a deposit.
        if ($listing->ai_price_status === 'underpriced' || ($listing->ai_price_score !== null && $listing->ai_price_score >= 90 && $listing->ai_price_status !== 'fair')) {
            $signals[] = ['key' => 'too_cheap', 'weight' => 30, 'reason' => 'Priced well below comparable listings'];
        }

        if ($seller && !$seller->kyc_verified) {
            $signals[] = ['key' => 'unverified_seller', 'weight' => 20, 'reason' => 'Seller has not completed identity verification'];
        }

        if ($seller && $seller->created_at?->gt(now()->subDays(7))) {
            $signals[] = ['key' => 'new_account', 'weight' => 15, 'reason' => 'Seller account is less than 7 days old'];
        }

        if ($seller && !$seller->hasVerifiedEmail()) {
            $signals[] = ['key' => 'unverified_email', 'weight' => 10, 'reason' => 'Seller email is not verified'];
        }

        if (empty($listing->images) && empty($listing->thumbnail)) {
            $signals[] = ['key' => 'no_photos', 'weight' => 10, 'reason' => 'Listing has no photos'];
        }

        $openReports = FraudReport::where('listing_id', $listing->id)->whereIn('status', ['pending', 'investigating'])->count();
        if ($openReports > 0) {
            $signals[] = ['key' => 'reported', 'weight' => min(40, 20 * $openReports), 'reason' => "{$openReports} open user report(s)"];
        }

        if ($listing->ai_trust_score !== null && $listing->ai_trust_score < 40) {
            $signals[] = ['key' => 'low_trust', 'weight' => 15, 'reason' => 'AI trust score below 40'];
        }

        return [
            'score' => min(100, array_sum(array_column($signals, 'weight'))),
            'signals' => $signals,
        ];
    }
}
