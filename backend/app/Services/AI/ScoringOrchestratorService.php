<?php

namespace App\Services\AI;

use App\Models\Listing;
use Illuminate\Support\Facades\Log;

/**
 * AI Decision Engine — scoring orchestrator.
 *
 * Coordinates PriceIntelligence -> Trust -> Demand -> Investment -> Deal
 * for a single listing. Every score here is derived from real data
 * (comparables, KYC, engagement, market trends) — never a random fallback.
 * OpenAI is used only to generate the human-readable insight sentence,
 * never to produce the numeric scores themselves.
 */
class ScoringOrchestratorService
{
    public function __construct(
        private PriceIntelligenceService $priceIntelligence,
        private TrustScoreService $trust,
        private DemandScoreService $demand,
        private InvestmentScoreService $investment,
        private DealScoreService $deal,
        private AIInsightService $insight,
    ) {
    }

    public function scoreListing(Listing $listing, bool $persist = true): array
    {
        $price = $this->priceIntelligence->analyze($listing);
        $trust = $this->trust->score($listing);
        $demand = $this->demand->score($listing);
        $investment = $this->investment->score($listing, $price, $demand['score']);
        $completeness = $this->completenessScore($listing);
        $deal = $this->deal->score($price, $trust['score'], $demand['score'], $investment['score'], $completeness);

        $insightText = '';
        try {
            $insightText = $this->insight->summarize($listing, $price, $trust, $demand, $investment);
        } catch (\Throwable $e) {
            Log::info('AI insight generation skipped: ' . $e->getMessage());
        }

        $result = [
            'has_estimate' => $price['has_estimate'],
            'price_source' => $price['source'] ?? 'comparables',
            'battery_score' => $price['has_estimate'] ? $price['battery_score'] : null,
            'battery_label' => $price['battery_label'],
            'price_status' => $price['price_status'],
            'market_avg' => $price['market_avg'],
            'market_min' => $price['market_min'],
            'market_max' => $price['market_max'],
            'confidence' => $price['confidence'],
            'comparables' => $price['comparable_count'],
            'trust_score' => $trust['score'],
            'demand_score' => $demand['score'],
            'invest_score' => $investment['score'],
            'deal_score' => $deal['score'],
            'insight' => $insightText,
            'reasons' => [
                'price' => $price['reasons'],
                'price_source' => $price['source'] ?? 'comparables',
                'trust' => $trust['reasons'],
                'demand' => $demand['reasons'],
                'investment' => $investment['reasons'],
            ],
            'breakdown' => $deal['breakdown'],
        ];

        if ($persist) {
            $listing->update([
                'ai_trust_score' => $trust['score'],
                'ai_investment_score' => $investment['score'],
                'ai_demand_score' => $demand['score'],
                'ai_deal_score' => $deal['score'],
                // No estimate (too few comparables) is stored as null, so the
                // UI shows "not enough data" instead of a made-up verdict.
                'ai_price_score' => $price['has_estimate'] ? $price['battery_score'] : null,
                'price_battery_percent' => $price['has_estimate'] ? $price['battery_score'] : null,
                'ai_price_status' => $price['price_status'],
                'ai_confidence_score' => $price['confidence'],
                'market_avg_price' => $price['market_avg'],
                'market_min_price' => $price['market_min'],
                'market_max_price' => $price['market_max'],
                'ai_comparable_ids' => $price['comparable_ids'],
                'ai_analysis' => $insightText ?: null,
                'ai_score_breakdown' => $result['reasons'],
                'ai_scored_at' => now(),
            ]);
        }

        return $result;
    }

    private function completenessScore(Listing $listing): int
    {
        $score = 30;
        $images = $listing->images ?? [];
        if (is_string($images)) {
            $images = json_decode($images, true) ?? [];
        }
        $imageCount = is_array($images) ? count($images) : 0;
        $score += min(30, $imageCount * 6);

        $descLength = strlen((string) $listing->description);
        $score += min(20, (int) ($descLength / 50));

        if ($listing->documents()->count() > 0) {
            $score += 10;
        }
        if ($listing->latitude && $listing->longitude) {
            $score += 10;
        }

        return (int) max(0, min(100, $score));
    }
}
