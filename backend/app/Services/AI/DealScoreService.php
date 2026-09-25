<?php

namespace App\Services\AI;

/**
 * DEAL SCORE™ — single weighted number a buyer can act on immediately.
 * Weights are configurable here (not hardcoded across the app) so an
 * admin-facing ranking-profile system can later override them per user intent.
 */
class DealScoreService
{
    private const WEIGHTS = [
        'price' => 0.35,
        'trust' => 0.20,
        'demand' => 0.20,
        'investment' => 0.15,
        'completeness' => 0.10,
    ];

    public function score(array $priceIntelligence, int $trustScore, int $demandScore, int $investmentScore, int $completenessScore): array
    {
        $weighted = [
            'price' => $priceIntelligence['battery_score'] * self::WEIGHTS['price'],
            'trust' => $trustScore * self::WEIGHTS['trust'],
            'demand' => $demandScore * self::WEIGHTS['demand'],
            'investment' => $investmentScore * self::WEIGHTS['investment'],
            'completeness' => $completenessScore * self::WEIGHTS['completeness'],
        ];

        $total = (int) round(array_sum($weighted));

        return [
            'score' => max(0, min(100, $total)),
            'breakdown' => [
                'price' => (int) round($priceIntelligence['battery_score']),
                'trust' => $trustScore,
                'demand' => $demandScore,
                'investment' => $investmentScore,
                'completeness' => $completenessScore,
            ],
            'weights' => self::WEIGHTS,
        ];
    }
}
