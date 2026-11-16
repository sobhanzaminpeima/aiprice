<?php

namespace App\Console\Commands;

use App\Models\Imports\ImportRun;
use App\Models\Imports\ImportSource;
use App\Models\Imports\SourceListing;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ListingImporter;
use Illuminate\Console\Command;

/**
 * php artisan imports:run kktcarabam --dry-run              discover, score, select (no listing writes)
 * php artisan imports:run kktcarabam --from-run=<dry-run>   re-confirm that selection, store media, create drafts
 * php artisan imports:run kktcarabam --publish-approved     publish drafts that pass PublicationPolicy
 * php artisan imports:run kktcarabam --feed=export.json --images=dir [--dry-run]   authorized export instead of pages
 *
 * Never scheduled automatically.
 */
class ImportSourceListings extends Command
{
    protected $signature = 'imports:run {source=kktcarabam}
        {--dry-run : Discover and preview only}
        {--from-run= : Import the selection of this dry-run}
        {--target=35 : Listings to select (20–50)}
        {--pages=4 : Index pages per category}
        {--max-fetch=90 : Maximum detail pages to fetch}
        {--publish-approved : Publish imported drafts that pass the publication rules}
        {--feed= : Use an operator-supplied JSON export instead of public pages}
        {--images= : Image directory for --feed}
        {--report= : Write the run report JSON to this path}';

    protected $description = 'Permission-aware import of third-party vehicle listings into AIPrice (drafts by default)';

    public function handle(ImportPipeline $pipeline, ListingImporter $listings): int
    {
        ImportSource::ensureKnown();
        $source = ImportSource::where('key', $this->argument('source'))->firstOrFail();

        if ($this->option('publish-approved')) {
            $published = 0;
            foreach (SourceListing::with('source')->where('import_source_id', $source->id)->where('status', 'imported')->get() as $sl) {
                try {
                    $listings->publish($sl);
                    $published++;
                } catch (\RuntimeException $e) {
                    $this->line("{$sl->source_listing_id}: {$e->getMessage()}");
                }
            }
            $this->info("Published {$published} listings.");

            return self::SUCCESS;
        }

        $target = (int) $this->option('target');
        if ($target < 20 || $target > 50) {
            $this->error('--target must be between 20 and 50.');

            return self::INVALID;
        }

        $run = ImportRun::create([
            'import_source_id' => $source->id, 'mode' => $this->option('dry-run') ? 'dry_run' : 'import',
            'status' => 'queued', 'target' => $target,
        ]);
        $this->info("Run {$run->id} ({$run->mode}) started for {$source->name}.");
        $adapter = $this->option('feed')
            ? new \App\Services\Imports\ExportFeedAdapter((string) $this->option('feed'), (string) $this->option('images'))
            : null;
        $run = $pipeline->run($run, $adapter, [
            'pages' => (int) $this->option('pages'), 'max_fetch' => (int) $this->option('max-fetch'),
            'from_run' => $this->option('from-run'),
        ]);

        $this->table(['metric', 'count'], collect(ImportRun::COUNTERS)->map(fn ($c) => [$c, $run->{$c}])->all());
        $this->line("Status: {$run->status}".($run->error ? " — {$run->error}" : ''));
        if ($path = $this->option('report')) {
            file_put_contents($path, json_encode(['run' => $run->toArray()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->line("Report written to {$path}");
        }

        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
