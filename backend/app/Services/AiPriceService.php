<?php

namespace App\Services;

use App\Models\Listing;
use App\Services\AI\PriceIntelligenceService;
use App\Services\AI\ScoringOrchestratorService;

/**
 * Thin facade kept for backward compatibility with existing route/controller
 * wiring. All real scoring logic now lives in App\Services\AI\*.
 *
 * @see \App\Services\AI\ScoringOrchestratorService
 */
class AiPriceService
{
    public function __construct(
        private ScoringOrchestratorService $orchestrator,
        private PriceIntelligenceService $priceIntelligence,
    ) {
    }

    public function analyzeListingPrice(Listing $listing): array
    {
        return $this->orchestrator->scoreListing($listing);
    }

    /**
     * Pre-listing estimate. No user/engagement history exists yet, so this
     * intentionally returns price intelligence only — trust/demand/invest
     * scores are not fabricated for a listing that doesn't exist yet.
     */
    public function estimatePrice(string $type, float $price, ?string $city = null): array
    {
        return $this->priceIntelligence->estimate($type, $price, $city);
    }
}
