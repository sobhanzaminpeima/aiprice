<?php

namespace App\Services\AI;

use Illuminate\Database\Eloquent\Builder;

/**
 * Dynamic Ranking Engine — the same listing set is ordered differently
 * depending on what the user is optimizing for. Weights live in ONE place
 * (here) rather than being duplicated across controllers/views, per the
 * product spec: "Do NOT hardcode these weights. Store configurable
 * ranking profiles."
 *
 * Ranks purely on the four score columns already persisted on `listings`
 * by ScoringOrchestratorService (ai_price_score, ai_trust_score,
 * ai_demand_score, ai_investment_score) so ordering happens at the SQL
 * level and stays efficient across large result sets.
 */
class RankingEngineService
{
    public const MODES = ['find', 'deal', 'invest'];

    private const PROFILES = [
        // Personal buyer: balance of fair price + trust, some investment upside.
        'find' => ['price' => 0.35, 'trust' => 0.30, 'demand' => 0.15, 'investment' => 0.20],
        // Bargain hunter: price dominates, trust guards against too-good-to-be-true.
        'deal' => ['price' => 0.55, 'trust' => 0.25, 'demand' => 0.15, 'investment' => 0.05],
        // Investor: investment potential dominates, price/trust are secondary checks.
        'invest' => ['price' => 0.15, 'trust' => 0.10, 'demand' => 0.15, 'investment' => 0.60],
    ];

    private const COLUMN_MAP = [
        'price' => 'ai_price_score',
        'trust' => 'ai_trust_score',
        'demand' => 'ai_demand_score',
        'investment' => 'ai_investment_score',
    ];

    public function weights(string $mode): array
    {
        return self::PROFILES[$mode] ?? self::PROFILES['find'];
    }

    public function applyToQuery(Builder $query, string $mode): Builder
    {
        $weights = $this->weights($mode);

        // Unscored listings fall back to a neutral 50 per dimension so they
        // aren't unfairly buried below scored ones, nor falsely boosted above them.
        $expression = collect($weights)
            ->map(fn ($weight, $key) => sprintf('(COALESCE(%s, 50) * %F)', self::COLUMN_MAP[$key], $weight))
            ->implode(' + ');

        return $query
            ->orderByRaw("({$expression}) DESC")
            ->orderByDesc('published_at');
    }
}
