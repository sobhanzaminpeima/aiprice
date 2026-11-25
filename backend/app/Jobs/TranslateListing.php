<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\AI\ListingTranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Translates one listing's description after it goes live. */
class TranslateListing implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;
    public int $backoff = 120;

    public function __construct(public string $listingId)
    {
        $this->onQueue('ai');
    }

    public function handle(ListingTranslationService $service): void
    {
        $listing = Listing::find($this->listingId);
        if ($listing && $listing->status === 'active') {
            $service->translate($listing);
        }
    }
}
