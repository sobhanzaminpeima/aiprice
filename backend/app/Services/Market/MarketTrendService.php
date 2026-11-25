<?php

namespace App\Services\Market;

use App\Models\CompetitorListing;
use App\Models\Listing;
use App\Models\Property;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Median asking price per month for listings similar to a given one, from our
 * own listings and from the market data collected from other sites.
 *
 * It reports what was asked, by the month a listing was posted. A month is shown
 * only when at least MIN_PER_MONTH prices back it, and a trend only when two or
 * more months qualify: otherwise the caller gets no points rather than a line
 * drawn through thin data.
 */
class MarketTrendService
{
    public const MIN_PER_MONTH = 3;
    public const MONTHS = 6;

    /** Statuses whose asking price is still a fair record of the market that month. */
    private const COUNTED = ['active', 'sold', 'rented', 'expired'];

    /** @return array{points: list<array{month: string, median: float, count: int}>, basis: string|null, change_pct: float|null, currency: string} */
    public function forListing(Listing $listing): array
    {
        $listing->loadMissing('listable');
        $currency = (string) $listing->currency;
        $since = now()->startOfMonth()->subMonths(self::MONTHS - 1);

        foreach ($this->tiers($listing) as $basis => $filter) {
            $rows = $this->rows($listing->type, $currency, $since, $filter);
            $points = $this->monthly($rows);
            if (count($points) >= 2) {
                return ['points' => $points, 'basis' => $basis, 'change_pct' => $this->change($points), 'currency' => $currency];
            }
        }

        return ['points' => [], 'basis' => null, 'change_pct' => null, 'currency' => $currency];
    }

    /** Narrowest comparison first: same model, then same brand, then the whole category. @return array<string, array<string, mixed>> */
    private function tiers(Listing $listing): array
    {
        $l = $listing->listable;
        $tiers = [];
        if ($l instanceof Vehicle) {
            $tiers['model'] = ['vehicle_model' => $l->model_id];
            $tiers['brand'] = ['vehicle_brand' => $l->brand_id];
        } elseif ($l instanceof Property) {
            if ($l->bedrooms !== null) {
                $tiers['type_and_size'] = ['property_type' => $l->property_type_id, 'bedrooms' => (int) $l->bedrooms];
            }
            $tiers['type'] = ['property_type' => $l->property_type_id];
        }
        $tiers['category'] = [];

        return $tiers;
    }

    /** @return Collection<int, array{at: \Carbon\CarbonInterface, price: float}> */
    private function rows(string $type, string $currency, \Carbon\CarbonInterface $since, array $filter): Collection
    {
        $own = Listing::query()
            ->where('type', $type)->where('currency', $currency)->where('price', '>', 0)
            ->whereIn('status', self::COUNTED)
            ->whereRaw('COALESCE(published_at, created_at) >= ?', [$since])
            ->when(isset($filter['vehicle_model']), fn ($q) => $q->whereHasMorph('listable', [Vehicle::class], fn ($v) => $v->where('model_id', $filter['vehicle_model'])))
            ->when(isset($filter['vehicle_brand']), fn ($q) => $q->whereHasMorph('listable', [Vehicle::class], fn ($v) => $v->where('brand_id', $filter['vehicle_brand'])))
            ->when(isset($filter['property_type']), fn ($q) => $q->whereHasMorph('listable', [Property::class], function ($p) use ($filter) {
                $p->where('property_type_id', $filter['property_type']);
                if (isset($filter['bedrooms'])) {
                    $p->where('bedrooms', $filter['bedrooms']);
                }
            }))
            ->get(['published_at', 'created_at', 'price'])
            ->map(fn ($l) => ['at' => $l->published_at ?? $l->created_at, 'price' => (float) $l->price]);

        // Market rows carry little structure, so they join only the broadest tier.
        $market = $filter === []
            ? CompetitorListing::where('type', $type)->where('currency', $currency)->where('price', '>', 0)
                ->where(fn ($q) => $q->whereNull('external_id')->orWhere('external_id', 'not like', 'gen-%'))
                ->whereRaw('COALESCE(listed_at, created_at) >= ?', [$since])
                ->get(['listed_at', 'created_at', 'price'])
                ->map(fn ($c) => ['at' => $c->listed_at ?? $c->created_at, 'price' => (float) $c->price])
            : collect();

        return $own->concat($market);
    }

    /** @return list<array{month: string, median: float, count: int}> */
    private function monthly(Collection $rows): array
    {
        return $rows->groupBy(fn ($r) => $r['at']->format('Y-m'))
            ->filter(fn ($g) => $g->count() >= self::MIN_PER_MONTH)
            ->map(fn ($g, $month) => ['month' => $month, 'median' => $this->median($g->pluck('price')->all()), 'count' => $g->count()])
            ->sortKeys()->values()->all();
    }

    private function change(array $points): ?float
    {
        $first = $points[0]['median'];
        $last = $points[count($points) - 1]['median'];

        return $first > 0 ? round(($last - $first) / $first * 100, 1) : null;
    }

    public function median(array $values): float
    {
        sort($values);
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }
        $mid = intdiv($n, 2);

        return round($n % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2, 2);
    }
}
