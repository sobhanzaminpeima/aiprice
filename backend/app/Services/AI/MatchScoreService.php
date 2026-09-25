<?php

namespace App\Services\AI;

use App\Models\Listing;

/**
 * AI Match Score — how well a listing fits a specific user's stated intent.
 * Unlike Deal/Trust/Investment scores this is never stored on the listing;
 * it is always computed against a preference payload built from search intent.
 *
 * Expected $preferences shape:
 * [
 *   'budget_max' => float|null,
 *   'budget_min' => float|null,
 *   'city' => string|null,
 *   'priorities' => ['price' => 0-100, 'trust' => 0-100, 'demand' => 0-100, 'investment' => 0-100],
 * ]
 */
class MatchScoreService
{
    public function score(Listing $listing, array $preferences, array $scores): array
    {
        $reasons = [];
        $points = 0;
        $maxPoints = 0;

        $budgetMax = $preferences['budget_max'] ?? null;
        $budgetMin = $preferences['budget_min'] ?? null;
        if ($budgetMax !== null) {
            $maxPoints += 30;
            if ($listing->price <= $budgetMax) {
                $points += 30;
                $reasons[] = 'Within your budget';
            } else {
                $overBy = (($listing->price - $budgetMax) / $budgetMax) * 100;
                $reasons[] = sprintf('%.0f%% over your budget', $overBy);
            }
        }
        if ($budgetMin !== null && $listing->price >= $budgetMin) {
            $maxPoints += 5;
            $points += 5;
        }

        $city = $preferences['city'] ?? null;
        if ($city) {
            $maxPoints += 15;
            if (strcasecmp($listing->city ?? '', $city) === 0) {
                $points += 15;
                $reasons[] = "Located in {$city}";
            }
        }

        $priorities = $preferences['priorities'] ?? [];
        $scoreMap = [
            'price' => $scores['price'] ?? 50,
            'trust' => $scores['trust'] ?? 50,
            'demand' => $scores['demand'] ?? 50,
            'investment' => $scores['investment'] ?? 50,
        ];

        foreach ($priorities as $key => $weight) {
            if (!isset($scoreMap[$key]) || $weight <= 0) {
                continue;
            }
            $weightNormalized = min(100, $weight) / 100; // 0..1
            $contribution = $weightNormalized * 50; // each priority worth up to 50 pts of the pool
            $maxPoints += $contribution;
            $earned = $contribution * ($scoreMap[$key] / 100);
            $points += $earned;

            if ($scoreMap[$key] >= 75) {
                $reasons[] = ucfirst($key) . ' score is strong (' . $scoreMap[$key] . '/100)';
            }
        }

        $matchPercent = $maxPoints > 0
            ? (int) round(($points / $maxPoints) * 100)
            : (int) round(($scoreMap['price'] + $scoreMap['trust']) / 2);

        return [
            'score' => max(0, min(100, $matchPercent)),
            'reasons' => array_values(array_unique($reasons)),
        ];
    }
}
