<?php

namespace App\Services\AI;

use App\Models\Listing;
use Illuminate\Support\Collection;

/**
 * AI Price Intelligence — market-comparable price scoring ("Price Battery").
 * Deterministic statistics only; no randomness. A confidence score always
 * accompanies the result so callers can tell "insufficient data" apart
 * from a genuine market read.
 */
class PriceIntelligenceService
{
    public function analyze(Listing $listing): array
    {
        $comparables = $this->findComparables($listing->type, $listing->city, $listing->id);

        return $this->buildResult($listing->price, $comparables);
    }

    /**
     * Pre-listing estimate: no Listing row exists yet, so comparables are
     * looked up purely by type/city.
     */
    public function estimate(string $type, float $price, ?string $city = null): array
    {
        $comparables = $this->findComparables($type, $city, null);

        return $this->buildResult($price, $comparables);
    }

    private function findComparables(string $type, ?string $city, ?string $excludeId): Collection
    {
        return Listing::where('type', $type)
            ->where('status', 'active')
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->when($city, fn ($q) => $q->where('city', $city))
            ->whereNotNull('price')
            ->orderByDesc('published_at')
            ->limit(30)
            ->get(['id', 'title', 'price', 'currency', 'city', 'published_at']);
    }

    private function buildResult(float $price, Collection $comparables): array
    {
        $count = $comparables->count();
        $avg = $count > 0 ? (float) $comparables->avg('price') : $price;
        $min = $count > 0 ? (float) $comparables->min('price') : $price * 0.85;
        $max = $count > 0 ? (float) $comparables->max('price') : $price * 1.15;

        $deviationPct = $avg > 0 ? (($price - $avg) / $avg) * 100 : 0;
        $battery = (int) max(5, min(98, round(100 - ($deviationPct * 1.5))));

        $status = match (true) {
            $battery >= 80 => 'fair',
            $battery >= 55 => 'slightly_overpriced',
            default => 'overpriced',
        };

        // Confidence scales with comparable count and caps at 95 — never a fabricated certainty.
        $confidence = $count === 0 ? 15 : (int) min(95, 40 + $count * 3);

        $reasons = [];
        if ($count > 0) {
            $reasons[] = "{$count} comparable listing(s) found";
            $reasons[] = sprintf('Average comparable price: %s', number_format($avg));
            $reasons[] = sprintf(
                '%.1f%% %s market average',
                abs($deviationPct),
                $deviationPct >= 0 ? 'above' : 'below'
            );
        } else {
            $reasons[] = 'No comparable listings found in this market segment yet';
        }

        return [
            'battery_score' => $battery,
            'battery_label' => $this->batteryLabel($battery),
            'price_status' => $status,
            'market_avg' => round($avg),
            'market_min' => round($min),
            'market_max' => round($max),
            'comparable_count' => $count,
            'comparable_ids' => $comparables->pluck('id')->take(5)->toArray(),
            'confidence' => $confidence,
            'reasons' => $reasons,
        ];
    }

    private function batteryLabel(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Excellent Price',
            $score >= 75 => 'Good Price',
            $score >= 55 => 'Fair Price',
            $score >= 35 => 'Slightly High',
            default => 'Overpriced',
        };
    }
}
