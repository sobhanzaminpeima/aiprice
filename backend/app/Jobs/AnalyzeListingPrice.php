<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\AiPriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs the scoring pipeline for one listing off the request thread. Unique per
 * listing so repeated clicks on "re-analyze" don't stack duplicate LLM calls.
 */
class AnalyzeListingPrice implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $uniqueFor = 300;

    public function __construct(public string $listingId)
    {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return $this->listingId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(AiPriceService $ai): void
    {
        $listing = Listing::find($this->listingId);

        // Deleted or unpublished since the job was queued — nothing to score.
        if (!$listing || in_array($listing->status, ['rejected', 'archived'], true)) {
            return;
        }

        $ai->analyzeListingPrice($listing);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('AI analysis failed permanently', ['listing_id' => $this->listingId, 'error' => $e->getMessage()]);
    }
}
