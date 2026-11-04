<?php

namespace App\Services\AI;

use App\Models\Listing;
use App\Models\MarketAnalytic;
use Illuminate\Support\Collection;

/**
 * Aggregates real listing/scoring data into the market-wide views the
 * admin "AI Intelligence Center" needs. Every number here is a database
 * aggregate — nothing hardcoded, nothing randomized. Segments with no
 * data return null fields rather than a fabricated placeholder so the
 * UI can render "insufficient data" honestly.
 */
class MarketInsightsService
{
    private const TYPES = ['vehicle', 'property', 'rental'];

    public function marketTrust(): array
    {
        return array_map(function (string $type) {
            $base = Listing::where('type', $type)->where('status', 'active');

            $confidence = (clone $base)->whereNotNull('ai_confidence_score')->avg('ai_confidence_score');
            $demand = (clone $base)->whereNotNull('ai_demand_score')->avg('ai_demand_score');
            $liquidity = $this->liquidityScore($type);

            $trend = MarketAnalytic::where('type', $type)
                ->orderByDesc('period_date')
                ->first();
            $growth = $trend?->price_trend;
            // Map a % price trend onto a 0-100 growth score (0% trend = 50, capped +-25%)
            $growthScore = $growth !== null ? (int) max(0, min(100, round(50 + ($growth * 2)))) : null;

            $breakdown = [
                ['name' => 'Confidence', 'val' => $confidence !== null ? (int) round($confidence) : null],
                ['name' => 'Demand', 'val' => $demand !== null ? (int) round($demand) : null],
                ['name' => 'Growth', 'val' => $growthScore],
                ['name' => 'Liquidity', 'val' => $liquidity],
            ];

            $available = collect($breakdown)->pluck('val')->filter(fn ($v) => $v !== null);
            $overall = $available->isNotEmpty() ? (int) round($available->avg()) : null;

            return [
                'type' => $type,
                'label' => match ($type) {
                    'vehicle' => 'Cars Market',
                    'property' => 'Properties Market',
                    default => 'Rental Market',
                },
                'score' => $overall,
                'breakdown' => $breakdown,
                'sample_size' => (clone $base)->count(),
            ];
        }, self::TYPES);
    }

    public function undervalued(int $limit = 5): Collection
    {
        return Listing::where('status', 'active')
            ->whereNotNull('market_avg_price')
            ->whereNotNull('ai_price_score')
            ->whereColumn('price', '<', 'market_avg_price')
            ->orderByDesc('ai_price_score')
            ->limit($limit)
            ->get(['id', 'title', 'type', 'price', 'currency', 'market_avg_price', 'city'])
            ->map(fn ($l) => $this->diffPayload($l));
    }

    public function overpriced(int $limit = 5): Collection
    {
        return Listing::where('status', 'active')
            ->whereNotNull('market_avg_price')
            ->whereNotNull('ai_price_score')
            ->whereColumn('price', '>', 'market_avg_price')
            ->orderBy('ai_price_score')
            ->limit($limit)
            ->get(['id', 'title', 'type', 'price', 'currency', 'market_avg_price', 'city'])
            ->map(fn ($l) => $this->diffPayload($l));
    }

    /**
     * Demand grouped by type + city — the finest segmentation the current
     * schema supports without a body-type/feature taxonomy. Coarser than
     * the eventual "SUVs vs Sedans" breakdown the product spec wants, but
     * every number here is real.
     */
    public function demandByCategory(int $limit = 6): Collection
    {
        return Listing::where('status', 'active')
            ->whereNotNull('ai_demand_score')
            ->whereNotNull('city')
            ->selectRaw('type, city, AVG(ai_demand_score) as avg_score, COUNT(*) as listing_count')
            ->groupBy('type', 'city')
            ->havingRaw('COUNT(*) >= 2')
            ->orderByDesc('avg_score')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'label' => ucfirst($row->type) . ' — ' . $row->city,
                'score' => (int) round($row->avg_score),
                'sample_size' => $row->listing_count,
            ]);
    }

    /**
     * Average days-on-market per type, from listings that actually sold/rented
     * (published_at -> updated_at as a proxy for close date). Types with fewer
     * than 3 closed listings are omitted rather than guessed.
     */
    public function fastSelling(): Collection
    {
        $overallAvg = Listing::whereIn('status', ['sold', 'rented'])
            ->whereNotNull('published_at')
            ->selectRaw('AVG('.$this->daysBetween().') as avg_days')
            ->value('avg_days');

        return collect(self::TYPES)->map(function (string $type) use ($overallAvg) {
            $stats = Listing::where('type', $type)
                ->whereIn('status', ['sold', 'rented'])
                ->whereNotNull('published_at')
                ->selectRaw('AVG('.$this->daysBetween().') as avg_days, COUNT(*) as closed_count')
                ->first();

            if (!$stats || $stats->closed_count < 3) {
                return [
                    'type' => ucfirst($type),
                    'avg_days' => null,
                    'closed_count' => $stats->closed_count ?? 0,
                    'hot' => false,
                ];
            }

            return [
                'type' => ucfirst($type),
                'avg_days' => round($stats->avg_days, 1),
                'closed_count' => $stats->closed_count,
                'hot' => $overallAvg !== null && $stats->avg_days < $overallAvg,
            ];
        })->filter(fn ($row) => $row['closed_count'] > 0)->values();
    }

    private function liquidityScore(string $type): ?int
    {
        $stats = Listing::where('type', $type)
            ->whereIn('status', ['sold', 'rented'])
            ->whereNotNull('published_at')
            ->selectRaw('AVG('.$this->daysBetween().') as avg_days, COUNT(*) as closed_count')
            ->first();

        if (!$stats || $stats->closed_count < 3 || $stats->avg_days === null) {
            return null;
        }

        // Faster average sale time = higher liquidity. 3 days -> ~95, 60+ days -> ~20.
        return (int) max(10, min(95, round(100 - ($stats->avg_days * 1.4))));
    }

    private function diffPayload(Listing $l): array
    {
        $diffPct = $l->market_avg_price > 0
            ? abs(($l->price - $l->market_avg_price) / $l->market_avg_price) * 100
            : 0;

        return [
            'id' => $l->id,
            'type' => $l->type,
            'title' => $l->title,
            'listed' => number_format($l->price) . ' ' . $l->currency,
            'avg' => number_format($l->market_avg_price) . ' ' . $l->currency,
            'diff' => round($diffPct, 1) . '%',
        ];
    }

    /** Days between publish and close, portable across MySQL and SQLite. */
    private function daysBetween(): string
    {
        return \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite'
            ? '(julianday(updated_at) - julianday(published_at))'
            : 'DATEDIFF(updated_at, published_at)';
    }
}
