<?php

namespace App\Services\AI;

use App\Models\Listing;
use Carbon\Carbon;

/**
 * Computes a 0-100 trust score from real, verifiable signals only.
 * No randomness — every point is traceable to a reason for the "Why?" panel.
 */
class TrustScoreService
{
    public function score(Listing $listing): array
    {
        $user = $listing->user;
        $reasons = [];
        $score = 30; // baseline for any published listing

        if ($user) {
            if ($user->kyc_verified) {
                $score += 25;
                $reasons[] = 'Seller identity verified (KYC)';
            }
            if ($user->is_verified) {
                $score += 10;
                $reasons[] = 'Verified account';
            }

            $accountAgeDays = $user->created_at ? (int) $user->created_at->diffInDays(now()) : 0;
            $ageBonus = (int) min(15, floor($accountAgeDays / 30) * 2); // +2 per month, cap 15
            if ($ageBonus > 0) {
                $score += $ageBonus;
                $reasons[] = "Account active for {$accountAgeDays} days";
            }

            $publishedCount = Listing::where('user_id', $user->id)
                ->where('status', 'active')
                ->count();
            if ($publishedCount > 1) {
                $historyBonus = (int) min(10, $publishedCount);
                $score += $historyBonus;
                $reasons[] = "{$publishedCount} listings published by this seller";
            }
        }

        if ($listing->is_verified) {
            $score += 5;
            $reasons[] = 'Listing manually verified';
        }

        $documentCount = $listing->documents()->count();
        if ($documentCount > 0) {
            $score += min(5, $documentCount * 2);
            $reasons[] = "{$documentCount} supporting document(s) uploaded";
        }

        $fraudReports = $listing->fraudReports()->where('status', '!=', 'dismissed')->get();
        if ($fraudReports->isNotEmpty()) {
            $penalty = min(60, $fraudReports->count() * 20);
            $score -= $penalty;
            $reasons[] = "{$fraudReports->count()} unresolved fraud report(s) — trust reduced";
        }

        $score = (int) max(0, min(100, round($score)));

        return [
            'score' => $score,
            'reasons' => $reasons,
        ];
    }
}
