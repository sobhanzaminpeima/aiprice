<?php

namespace App\Services\AI;

use App\Models\Listing;

/**
 * Computes a 0-100 demand score from engagement signals relative to
 * comparable listings (same type + city), so it reflects real market
 * interest rather than an arbitrary constant.
 */
class DemandScoreService
{
    public function score(Listing $listing): array
    {
        $reasons = [];

        $daysLive = $listing->published_at ? max(1, $listing->published_at->diffInDays(now())) : 1;
        $viewsPerDay = $listing->view_count / $daysLive;
        $inquiryRate = $listing->view_count > 0 ? $listing->inquiry_count / $listing->view_count : 0;

        $comparableAvgViews = Listing::where('type', $listing->type)
            ->where('status', 'active')
            ->where('id', '!=', $listing->id)
            ->when($listing->city, fn ($q) => $q->where('city', $listing->city))
            ->avg('view_count') ?? 0;

        $score = 40; // neutral baseline

        if ($comparableAvgViews > 0) {
            $ratio = $listing->view_count / max(1, $comparableAvgViews);
            $viewBonus = (int) min(30, round(($ratio - 1) * 20));
            $score += $viewBonus;
            $reasons[] = $ratio >= 1
                ? sprintf('%.0f%% more views than similar listings', ($ratio - 1) * 100)
                : sprintf('%.0f%% fewer views than similar listings', (1 - $ratio) * 100);
        } elseif ($listing->view_count > 0) {
            $score += min(15, (int) round($viewsPerDay * 3));
            $reasons[] = "{$listing->view_count} views since published";
        }

        if ($listing->favorite_count > 0) {
            $score += min(15, $listing->favorite_count * 3);
            $reasons[] = "{$listing->favorite_count} buyers saved this listing";
        }

        if ($inquiryRate > 0.02) {
            $score += min(15, (int) round($inquiryRate * 300));
            $reasons[] = 'Above-average inquiry rate';
        }

        $score = (int) max(0, min(100, round($score)));

        return [
            'score' => $score,
            'reasons' => $reasons,
        ];
    }
}
