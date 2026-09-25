<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Market figures computed from AIPrice's own active listings and their AI
 * scores. Nothing here is estimated or sampled: if a segment has no scored
 * listings, its index is null and the UI says so.
 */
class MarketAnalyticsController extends Controller
{
    public function overview(): JsonResponse
    {
        $active = fn () => Listing::query()->where('status', 'active');

        $indexes = $active()
            ->select('type',
                DB::raw('COUNT(*) as listings'),
                DB::raw('COUNT(ai_scored_at) as scored'),
                DB::raw('AVG(ai_trust_score) as trust'),
                DB::raw('AVG(ai_demand_score) as demand'),
                DB::raw('AVG(ai_deal_score) as deal'),
                DB::raw('AVG(ai_investment_score) as investment'))
            ->groupBy('type')
            ->get()
            ->map(function ($row) {
                $parts = array_filter([$row->trust, $row->demand, $row->deal], fn ($v) => $v !== null);
                return [
                    'type' => $row->type,
                    'listings' => (int) $row->listings,
                    'scored' => (int) $row->scored,
                    'index' => $parts ? round(array_sum($parts) / count($parts)) : null,
                    'trust' => $this->r($row->trust),
                    'demand' => $this->r($row->demand),
                    'deal' => $this->r($row->deal),
                    'investment' => $this->r($row->investment),
                ];
            });

        $cities = $active()
            ->whereNotNull('city')
            ->select('type', 'city', 'currency',
                DB::raw('COUNT(*) as listings'),
                DB::raw('AVG(price) as avg_price'),
                DB::raw('MIN(price) as min_price'),
                DB::raw('MAX(price) as max_price'))
            ->groupBy('type', 'city', 'currency')
            ->orderByDesc('listings')
            ->get()
            ->map(fn ($r) => [
                'type' => $r->type, 'city' => $r->city, 'currency' => $r->currency,
                'listings' => (int) $r->listings,
                'avg_price' => round((float) $r->avg_price), 'min_price' => (float) $r->min_price, 'max_price' => (float) $r->max_price,
                'trend_pct' => $this->cityTrend($r->type, $r->city, $r->currency),
            ]);

        $priceStatus = $active()
            ->whereNotNull('ai_price_status')
            ->select('ai_price_status', DB::raw('COUNT(*) as n'))
            ->groupBy('ai_price_status')
            ->pluck('n', 'ai_price_status');

        $opportunities = $active()
            ->whereNotNull('ai_deal_score')
            ->orderByDesc('ai_deal_score')
            ->limit(6)
            ->get(['id', 'slug', 'type', 'title', 'city', 'price', 'currency', 'ai_deal_score', 'ai_price_status', 'ai_investment_score']);

        $weekly = $active()
            ->where('created_at', '>=', now()->subWeeks(12)->startOfWeek())
            ->get(['type', 'created_at'])
            ->groupBy(fn ($l) => $l->created_at->startOfWeek()->format('Y-m-d'))
            ->map(fn ($g) => $g->countBy('type'))
            ->sortKeys();

        return response()->json([
            'indexes' => $indexes,
            'cities' => $cities,
            'price_status' => $priceStatus,
            'opportunities' => $opportunities,
            'new_listings_weekly' => $weekly,
        ]);
    }

    /**
     * Average asking price in the last 90 days vs the 90 days before, for one
     * city. Null when either window is empty — no invented trend.
     */
    private function cityTrend(string $type, string $city, ?string $currency): ?float
    {
        $window = fn ($from, $to) => Listing::where('type', $type)->where('city', $city)->where('currency', $currency)
            ->whereBetween('created_at', [$from, $to])->avg('price');

        $recent = $window(now()->subDays(90), now());
        $prior = $window(now()->subDays(180), now()->subDays(90));

        return ($recent && $prior) ? round(($recent - $prior) / $prior * 100, 1) : null;
    }

    private function r($v): ?float
    {
        return $v === null ? null : round((float) $v, 1);
    }
}
