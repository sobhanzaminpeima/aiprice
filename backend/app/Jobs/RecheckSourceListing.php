<?php

namespace App\Jobs;

use App\Models\Imports\SourceListing;
use App\Services\Imports\ImportPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Re-check one imported listing at its source (active? price changed?). */
class RecheckSourceListing implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public string $sourceListingId)
    {
        $this->onQueue('crawl');
    }

    public function uniqueId(): string
    {
        return $this->sourceListingId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(ImportPipeline $pipeline): void
    {
        $sl = SourceListing::with('source')->find($this->sourceListingId);
        if (! $sl || $sl->source->isPaused()) {
            return;
        }
        $result = $pipeline->recheck($sl);
        if (in_array($result['status'], ['error'], true)) {
            throw new \RuntimeException('Recheck failed: '.($result['error'] ?? 'unknown'));
        }
    }
}
