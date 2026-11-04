<?php

namespace App\Services\AI;

use App\Models\CompetitorListing;
use App\Models\Listing;
use App\Models\Property;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Support\Collection;

/**
 * AI Price Intelligence: market-comparable price estimate ("Price Battery").
 *
 * Deterministic statistics only; no randomness and no LLM. Prices are compared
 * against similar listings on AIPrice.app plus market data imported from other
 * sites (competitor_listings, used for analysis only and never shown).
 *
 * Honesty rules:
 *  - Only listings in the SAME currency are compared (no silent FX mixing).
 *  - Similarity is tiered: vehicles by model/brand and year, properties by
 *    type, bedrooms and size, then widened step by step. The tier used is
 *    reported in the reasons.
 *  - Fewer than MIN_COMPARABLES similar prices gives "insufficient_data";
 *    no "good price" verdict is invented from nothing.
 *  - The median (robust to outliers) is the market reference.
 *  - The result is an estimate; the confidence never exceeds 90.
 */
class PriceIntelligenceService
{
    public const MIN_COMPARABLES = 3;

    private const POOL_LIMIT = 400;

    public function analyze(Listing $listing): array
    {
        $listing->loadMissing('listable');
        $traits = $this->traits($listing->listable, true);
        $pool = $this->pool($listing->type, (string) $listing->currency, $listing->id);

        return $this->buildResult((float) $listing->price, (string) $listing->currency, $listing->city, $traits, $pool);
    }

    /** Pre-listing estimate: no Listing row exists yet. */
    public function estimate(string $type, float $price, ?string $city = null, string $currency = 'GBP', array $traits = []): array
    {
        $pool = $this->pool($type, $currency, null);

        return $this->buildResult($price, $currency, $city, $traits, $pool);
    }

    /** Normalised attributes used for similarity matching. */
    private function traits(mixed $listable, bool $withNames = false): array
    {
        if ($listable instanceof Vehicle) {
            $t = ['brand_id' => $listable->brand_id, 'model_id' => $listable->model_id,
                'year' => $listable->year ? (int) $listable->year : null];
            if ($withNames) {
                $t['brand_name'] = mb_strtolower((string) VehicleBrand::whereKey($listable->brand_id)->value('name'));
                $t['model_name'] = mb_strtolower((string) VehicleModel::whereKey($listable->model_id)->value('name'));
            }

            return $t;
        }
        if ($listable instanceof Property) {
            return ['property_type_id' => $listable->property_type_id,
                'bedrooms' => $listable->bedrooms !== null ? (int) $listable->bedrooms : null,
                'area_sqm' => $listable->area_sqm ? (float) $listable->area_sqm : null];
        }

        return [];
    }

    /**
     * Candidate comparables: same type and currency, active, priced.
     * Each row: id, price, city, source ('aiprice'|'market'), traits.
     */
    private function pool(string $type, string $currency, ?string $excludeId): Collection
    {
        $own = Listing::with('listable')
            ->where('type', $type)
            ->where('status', 'active')
            ->where('currency', $currency)
            ->where('price', '>', 0)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderByDesc('published_at')
            ->limit(self::POOL_LIMIT)
            ->get()
            ->map(fn (Listing $l) => [
                'id' => $l->id, 'price' => (float) $l->price, 'city' => $l->city, 'source' => 'aiprice',
                'traits' => $this->traits($l->listable),
            ]);

        $market = CompetitorListing::where('type', $type)
            ->where('is_active', true)
            ->where('currency', $currency)
            ->where('price', '>', 0)
            // Rows prefixed "gen-" were fabricated by an old crawler fallback.
            ->where(fn ($q) => $q->whereNull('external_id')->orWhere('external_id', 'not like', 'gen-%'))
            ->orderByDesc('listed_at')
            ->limit(self::POOL_LIMIT)
            ->get()
            ->map(function (CompetitorListing $c) {
                $a = is_array($c->attributes) ? $c->attributes : [];

                return [
                    'id' => null, 'price' => (float) $c->price, 'city' => $c->city, 'source' => 'market',
                    'traits' => [
                        'year' => isset($a['year']) ? (int) $a['year'] : null,
                        'bedrooms' => isset($a['bedrooms']) ? (int) $a['bedrooms'] : null,
                        'area_sqm' => isset($a['area_sqm']) ? (float) $a['area_sqm'] : (isset($a['area']) ? (float) $a['area'] : null),
                        'title' => mb_strtolower((string) $c->title),
                    ],
                ];
            });

        return $own->concat($market)->values();
    }

    /** Ordered similarity tiers, strictest first. */
    private function tiers(array $t, ?string $city): array
    {
        $sameCity = fn ($c) => $city && $c['city'] && mb_strtolower($c['city']) === mb_strtolower($city);
        $yearNear = fn ($c, $d) => empty($t['year']) || empty($c['traits']['year']) || abs($c['traits']['year'] - $t['year']) <= $d;
        $bedsNear = fn ($c, $d) => ! isset($t['bedrooms']) || ! isset($c['traits']['bedrooms']) || abs($c['traits']['bedrooms'] - $t['bedrooms']) <= $d;
        $areaNear = fn ($c, $pct) => empty($t['area_sqm']) || empty($c['traits']['area_sqm']) || abs($c['traits']['area_sqm'] - $t['area_sqm']) / $t['area_sqm'] <= $pct;
        $typeSame = fn ($c) => ! isset($c['traits']['property_type_id']) || $c['traits']['property_type_id'] === ($t['property_type_id'] ?? null);

        if (! empty($t['brand_id'])) {
            $brand = $t['brand_name'] ?? '';
            $model = $t['model_name'] ?? '';
            // Market rows have no ids, so match them on the title text.
            $sameModel = fn ($c) => $c['source'] === 'market'
                ? ($brand !== '' && $model !== '' && str_contains($c['traits']['title'], $brand) && str_contains($c['traits']['title'], $model))
                : ($c['traits']['model_id'] ?? null) === $t['model_id'];
            $sameBrand = fn ($c) => $c['source'] === 'market'
                ? ($brand !== '' && str_contains($c['traits']['title'], $brand))
                : ($c['traits']['brand_id'] ?? null) === $t['brand_id'];

            return [
                ['label' => 'same model, ±2 years, same city', 'match' => fn ($c) => $sameModel($c) && $yearNear($c, 2) && $sameCity($c)],
                ['label' => 'same model, ±3 years', 'match' => fn ($c) => $sameModel($c) && $yearNear($c, 3)],
                ['label' => 'same brand, ±3 years', 'match' => fn ($c) => $sameBrand($c) && $yearNear($c, 3)],
            ];
        }
        if (! empty($t['property_type_id']) || isset($t['bedrooms'])) {
            return [
                ['label' => 'same type and bedrooms, similar size, same city', 'match' => fn ($c) => $typeSame($c) && $bedsNear($c, 0) && $areaNear($c, 0.25) && $sameCity($c)],
                ['label' => 'same type, ±1 bedroom, similar size', 'match' => fn ($c) => $typeSame($c) && $bedsNear($c, 1) && $areaNear($c, 0.35)],
                ['label' => 'same type in the same city', 'match' => fn ($c) => $typeSame($c) && $sameCity($c)],
            ];
        }

        return [['label' => 'same category in the same city', 'match' => $sameCity]];
    }

    private function buildResult(float $price, string $currency, ?string $city, array $traits, Collection $pool): array
    {
        $chosen = collect();
        $tierLabel = null;
        foreach ($this->tiers($traits, $city) as $tier) {
            $matches = $pool->filter($tier['match'])->values();
            if ($matches->count() >= self::MIN_COMPARABLES) {
                $chosen = $matches;
                $tierLabel = $tier['label'];
                break;
            }
        }

        if ($chosen->count() < self::MIN_COMPARABLES || $price <= 0) {
            return [
                'has_estimate' => false,
                // Neutral value for downstream composite scores; never shown as a verdict.
                'battery_score' => 50,
                'battery_label' => 'Not enough data',
                'price_status' => 'insufficient_data',
                'market_avg' => null, 'market_min' => null, 'market_max' => null,
                'comparable_count' => 0,
                'comparable_ids' => [],
                'confidence' => 10,
                'reasons' => [
                    sprintf('Fewer than %d similar %s listings found, so no price estimate is shown yet.', self::MIN_COMPARABLES, $currency),
                    'An estimate appears automatically once enough comparable listings and market data exist.',
                ],
            ];
        }

        $prices = $this->trimOutliers($chosen->pluck('price')->sort()->values());
        $median = $this->percentile($prices, 50);
        $p25 = $this->percentile($prices, 25);
        $p75 = $this->percentile($prices, 75);

        $deviationPct = (($price - $median) / $median) * 100;
        // 100 at or below the median, falling 1.5 points per % above it.
        $battery = (int) max(5, min(98, round(100 - max(0, $deviationPct) * 1.5)));
        $status = match (true) {
            $deviationPct <= 10 => 'fair',
            $deviationPct <= 25 => 'slightly_overpriced',
            default => 'overpriced',
        };

        $marketCount = $chosen->where('source', 'market')->count();
        $spread = $median > 0 ? ($p75 - $p25) / $median : 1;
        $confidence = (int) max(20, min(90, 30 + $prices->count() * 4 - (int) round($spread * 40)));

        $reasons = [
            sprintf('Compared with %d similar listing(s): %s.', $prices->count(), $tierLabel),
            sprintf('Typical asking price: %s %s (middle half %s–%s).', $currency, number_format($median), number_format($p25), number_format($p75)),
            sprintf('This price is %.1f%% %s the typical asking price.', abs($deviationPct), $deviationPct >= 0 ? 'above' : 'below'),
        ];
        if ($marketCount > 0) {
            $reasons[] = "{$marketCount} of these come from wider North Cyprus market data.";
        }
        if ($deviationPct < -30) {
            $reasons[] = 'Much cheaper than similar listings: check the details and documents carefully before paying anything.';
        }
        $reasons[] = 'This is an estimate based on asking prices, not a valuation or a guarantee.';

        return [
            'has_estimate' => true,
            'battery_score' => $battery,
            'battery_label' => $this->batteryLabel($battery, $deviationPct),
            'price_status' => $status,
            'market_avg' => round($median),
            'market_min' => round($p25),
            'market_max' => round($p75),
            'comparable_count' => $prices->count(),
            'comparable_ids' => $chosen->pluck('id')->filter()->take(5)->values()->toArray(),
            'confidence' => $confidence,
            'reasons' => $reasons,
        ];
    }

    /** Drop values outside 1.5×IQR once there is enough data to tell. */
    private function trimOutliers(Collection $sorted): Collection
    {
        if ($sorted->count() < 6) {
            return $sorted;
        }
        $q1 = $this->percentile($sorted, 25);
        $q3 = $this->percentile($sorted, 75);
        $iqr = $q3 - $q1;
        $kept = $sorted->filter(fn ($p) => $p >= $q1 - 1.5 * $iqr && $p <= $q3 + 1.5 * $iqr)->values();

        return $kept->count() >= self::MIN_COMPARABLES ? $kept : $sorted;
    }

    private function percentile(Collection $sorted, float $pct): float
    {
        $n = $sorted->count();
        if ($n === 0) {
            return 0.0;
        }
        $pos = ($n - 1) * $pct / 100;
        $lo = (int) floor($pos);
        $hi = (int) ceil($pos);

        return (float) ($sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * ($pos - $lo));
    }

    private function batteryLabel(int $score, float $deviationPct): string
    {
        return match (true) {
            $deviationPct <= -10 => 'Below typical price',
            $score >= 90 => 'Good price (estimate)',
            $score >= 80 => 'Fair price (estimate)',
            $score >= 55 => 'Slightly high',
            default => 'High for this market',
        };
    }
}
