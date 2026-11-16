<?php

namespace App\Jobs;

use App\Models\Imports\ImportRun;
use App\Services\Imports\ImportPipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One dry-run or import run. Not retried automatically: a run talks to an
 * external site, and a blind retry could repeat requests the source refused.
 * Per-listing failures are isolated inside the pipeline instead.
 */
class RunSourceImport implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct(public string $runId, public array $options = [])
    {
        $this->onQueue('crawl');
    }

    public function uniqueId(): string
    {
        return ImportRun::whereKey($this->runId)->value('import_source_id') ?? $this->runId;
    }

    public function handle(ImportPipeline $pipeline): void
    {
        $run = ImportRun::find($this->runId);
        if ($run && $run->status === 'queued') {
            $pipeline->run($run, null, $this->options);
        }
    }

    public function failed(\Throwable $e): void
    {
        ImportRun::whereKey($this->runId)->whereIn('status', ['queued', 'running'])
            ->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now()]);
    }
}
