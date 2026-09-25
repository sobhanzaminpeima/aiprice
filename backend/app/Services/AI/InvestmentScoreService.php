<?php

namespace App\Services\AI;

use App\Models\Listing;
use App\Models\MarketAnalytic;

/**
 * Investment potential score. Uses real market_analytics trend data when
 * available; otherwise returns a neutral score with an explicit low
 * confidence flag rather than a fabricated number.
 */
class InvestmentScoreService
{
    public function score(Listing $listing, array $priceIntelligence, int $demandScore): array
    {
        $reasons = [];
        $score = 50; // neutral baseline
        $confidence = 30;

        $trend = MarketAnalytic::where('type', $listing->type)
            ->when($listing->city, fn ($q) => $q->where('city', $listing->city))
            ->orderByDesc('period_date')
            ->first();

        if ($trend && $trend->price_trend !== null) {
            $trendBonus = (int) max(-20, min(25, round($trend->price_trend * 4)));
            $score += $trendBonus;
            $confidence += 30;
            $reasons[] = sprintf(
                'Market price trend: %s%.1f%% recently',
                $trend->price_trend >= 0 ? '+' : '',
                $trend->price_trend
            );

            if ($trend->demand_index !== null) {
                $demandBonus = (int) round(($trend->demand_index - 50) * 0.2);
                $score += $demandBonus;
                $confidence += 15;
                $reasons[] = sprintf('Market demand index: %.0f/100', $trend->demand_index);
            }
        } else {
            $reasons[] = 'No market trend data yet for this segment — using listing signals only';
        }

        // Under-priced relative to market = better entry point for an investor.
        if ($priceIntelligence['battery_score'] >= 80) {
            $score += 10;
            $reasons[] = 'Priced below or at market average — favorable entry point';
        } elseif ($priceIntelligence['battery_score'] < 55) {
            $score -= 10;
            $reasons[] = 'Priced above market average — weaker entry point';
        }

        // Strong demand supports resale/rental liquidity.
        if ($demandScore >= 65) {
            $score += 8;
            $reasons[] = 'Strong buyer interest supports liquidity';
        }

        $score = (int) max(0, min(100, round($score)));
        $confidence = (int) min(90, $confidence);

        return [
            'score' => $score,
            'confidence' => $confidence,
            'reasons' => $reasons,
        ];
    }
}
