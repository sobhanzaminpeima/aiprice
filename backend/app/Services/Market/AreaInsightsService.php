<?php

namespace App\Services\Market;

use App\Models\Listing;

/**
 * Per-town snapshot for the home page: how many listings, the typical asking
 * price, and how it moved over the last 90 days. A change is reported only when
 * both 90-day windows hold enough listings; otherwise it is null.
 */
class AreaInsightsService
{
    private const MIN_FOR_CHANGE = 3;

    /** Median asking-price change over the last 90 days across all active listings in one currency. */
    public function overall(string $currency = 'GBP'): array
    {
        $trend = app(MarketTrendService::class);
        $rows = Listing::where('status', 'active')->where('currency', $currency)->where('price', '>', 0)->get(['price', 'published_at', 'created_at', 'type']);
        $change = null;
        // Compare like with like: a type contributes only when both windows hold enough of it.
        $parts = [];
        foreach ($rows->groupBy('type') as $group) {
            $recent = $group->filter(fn ($l) => ($l->published_at ?? $l->created_at)->gte(now()->subDays(90)))->pluck('price')->map(fn ($p) => (float) $p)->all();
            $before = $group->filter(fn ($l) => ($l->published_at ?? $l->created_at)->between(now()->subDays(180), now()->subDays(90)))->pluck('price')->map(fn ($p) => (float) $p)->all();
            if (count($recent) >= self::MIN_FOR_CHANGE && count($before) >= self::MIN_FOR_CHANGE && $trend->median($before) > 0) {
                $parts[] = [($trend->median($recent) - $trend->median($before)) / $trend->median($before) * 100, count($recent) + count($before)];
            }
        }
        if ($parts) {
            $weight = array_sum(array_column($parts, 1));
            $change = round(array_sum(array_map(fn ($p) => $p[0] * $p[1], $parts)) / $weight, 1);
        }

        return ['change_90d' => $change, 'listings' => $rows->count(), 'currency' => $currency];
    }

    /** @return list<array{city: string, listings: int, median_price: float, currency: string, change_90d: float|null, density: string}> */
    public function summary(?string $type = null, string $currency = 'GBP'): array
    {
        $trend = app(MarketTrendService::class);
        $rows = Listing::where('status', 'active')->where('currency', $currency)->where('price', '>', 0)
            ->whereNotNull('city')->where('city', '!=', '')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->get(['city', 'price', 'published_at', 'created_at']);

        $areas = $rows->groupBy(fn ($l) => mb_convert_case(trim($l->city), MB_CASE_TITLE))->map(function ($g, $city) use ($trend, $currency) {
            $recent = $g->filter(fn ($l) => ($l->published_at ?? $l->created_at)->gte(now()->subDays(90)))->pluck('price')->map(fn ($p) => (float) $p)->all();
            $before = $g->filter(fn ($l) => ($l->published_at ?? $l->created_at)->between(now()->subDays(180), now()->subDays(90)))->pluck('price')->map(fn ($p) => (float) $p)->all();
            $change = count($recent) >= self::MIN_FOR_CHANGE && count($before) >= self::MIN_FOR_CHANGE && $trend->median($before) > 0
                ? round(($trend->median($recent) - $trend->median($before)) / $trend->median($before) * 100, 1) : null;

            return [
                'city' => (string) $city, 'listings' => $g->count(),
                'median_price' => $trend->median($g->pluck('price')->map(fn ($p) => (float) $p)->all()),
                'currency' => $currency, 'change_90d' => $change,
            ];
        })->sortByDesc('listings')->values();

        $max = max(1, (int) $areas->max('listings'));

        return $areas->take(8)->map(fn ($a) => $a + ['density' => $a['listings'] / $max >= 0.66 ? 'high' : ($a['listings'] / $max >= 0.33 ? 'medium' : 'low')])->all();
    }
}
