<?php

namespace App\Services\AI;

use App\Models\Listing;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * "Show Me Better" — finds real alternatives that genuinely beat a listing
 * on at least one dimension (price, deal score, or trust), with a
 * grounded reason for each. Never returns a "better" match without a
 * concrete, comparable-data-backed justification.
 */
class AlternativesService
{
    public function findBetter(Listing $listing, int $limit = 3): Collection
    {
        $priceLow = $listing->price * 0.6;
        $priceHigh = $listing->price * 1.4;
        $baseDeal = (float) ($listing->ai_deal_score ?? 50);
        $baseTrust = (float) ($listing->ai_trust_score ?? 50);

        $candidates = Listing::with('listable')
            ->where('type', $listing->type)
            ->where('status', 'active')
            ->where('id', '!=', $listing->id)
            ->when($listing->city, fn ($q) => $q->where('city', $listing->city))
            ->whereBetween('price', [$priceLow, $priceHigh])
            ->where(function ($q) use ($baseDeal, $baseTrust, $listing) {
                // Any one of these alone is a valid "better" reason. A NULL
                // score must never silently disqualify an otherwise-cheaper
                // candidate — cheaper-but-unscored still counts as better.
                $q->where('price', '<', $listing->price)
                    ->orWhere('ai_deal_score', '>', $baseDeal)
                    ->orWhere('ai_trust_score', '>=', $baseTrust + 5);
            })
            ->orderByRaw('COALESCE(ai_deal_score, 0) DESC')
            ->limit($limit * 3) // overfetch, then rank by strength of the reasons below
            ->get();

        return $candidates
            ->map(fn (Listing $c) => $this->compare($listing, $c))
            ->filter(fn ($row) => !empty($row['reasons']))
            ->sortByDesc(fn ($row) => count($row['reasons']))
            ->take($limit)
            ->values();
    }

    private function compare(Listing $original, Listing $candidate): array
    {
        $reasons = [];

        if ($candidate->price < $original->price) {
            $diffPct = $original->price > 0 ? (($original->price - $candidate->price) / $original->price) * 100 : 0;
            $reasons[] = sprintf('%s %s cheaper', number_format($original->price - $candidate->price), $candidate->currency)
                . sprintf(' (%.1f%% less)', $diffPct);
        }

        if (($candidate->ai_deal_score ?? null) !== null && ($original->ai_deal_score ?? null) !== null
            && $candidate->ai_deal_score > $original->ai_deal_score) {
            $reasons[] = sprintf('%d%% higher deal score', round($candidate->ai_deal_score - $original->ai_deal_score));
        }

        if (($candidate->ai_trust_score ?? null) !== null && ($original->ai_trust_score ?? null) !== null
            && $candidate->ai_trust_score > $original->ai_trust_score + 5) {
            $reasons[] = sprintf('%d%% higher seller trust', round($candidate->ai_trust_score - $original->ai_trust_score));
        }

        if ($original->type === 'vehicle'
            && $original->listable instanceof Vehicle
            && $candidate->listable instanceof Vehicle
            && $original->listable->mileage && $candidate->listable->mileage
            && $candidate->listable->mileage < $original->listable->mileage) {
            $reasons[] = sprintf('%s km less mileage', number_format($original->listable->mileage - $candidate->listable->mileage));
        }

        return [
            'id' => $candidate->id,
            'slug' => $candidate->slug,
            'title' => $candidate->title,
            'price' => $candidate->price,
            'currency' => $candidate->currency,
            'thumbnail' => $candidate->thumbnail,
            'deal_score' => $candidate->ai_deal_score,
            'reasons' => $reasons,
        ];
    }
}
