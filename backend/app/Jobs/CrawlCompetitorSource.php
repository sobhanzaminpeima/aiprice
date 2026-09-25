<?php

namespace App\Jobs;

use App\Models\CompetitorSource;
use App\Services\CompetitorCrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Crawls one competitor site. One job per source so a slow site can't hold up the rest. */
class CrawlCompetitorSource implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;
    public int $uniqueFor = 3600;
    public int $backoff = 300;

    public function __construct(public string $sourceId)
    {
        $this->onQueue('crawl');
    }

    public function uniqueId(): string
    {
        return $this->sourceId;
    }

    public function handle(CompetitorCrawlerService $crawler): void
    {
        $source = CompetitorSource::find($this->sourceId);

        if ($source?->is_active) {
            $crawler->crawlSource($source);
        }
    }
}
