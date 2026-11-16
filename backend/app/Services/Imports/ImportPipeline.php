<?php

namespace App\Services\Imports;

use App\Models\Imports\ImportRun;
use App\Models\Imports\ImportSource;
use App\Models\Imports\SourceListing;
use App\Models\Listing;
use App\Services\Imports\Contracts\SourceAdapter;
use App\Services\Imports\Kktcarabam\KktcarabamAdapter;
use App\Services\Imports\Kktcarabam\KktcarabamMapper;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Orchestrates one import run. Modes:
 *  - dry_run: discover, fetch, score and select. Writes only staging rows
 *    (source_listings / import_runs) — never listings, vehicles or media.
 *  - import:  same as dry_run, or (with from_run) re-confirms the rows a
 *    previous dry run selected, then stores media privately and creates
 *    DRAFT listings. Optional `publish` publishes only rows that pass
 *    PublicationPolicy.
 *
 * Each candidate is isolated: one failure is recorded on its row and the run
 * continues. A source block (403/429/challenge) stops the run immediately.
 */
class ImportPipeline
{
    public function __construct(
        private CandidateScorer $scorer,
        private CandidateSelector $selector,
        private ImageImporter $images,
        private ListingImporter $listings,
        private PublicationPolicy $policy,
    ) {}

    public function adapterFor(ImportSource $source): SourceAdapter
    {
        return match ($source->key) {
            'kktcarabam' => app(KktcarabamAdapter::class)->withDelay($source->request_delay_ms),
            default => throw new InvalidArgumentException("No adapter for source {$source->key}"),
        };
    }

    /**
     * @param  array{pages?: int, max_fetch?: int, from_run?: ?string, publish?: bool}  $opts
     */
    public function run(ImportRun $run, ?SourceAdapter $adapter = null, array $opts = []): ImportRun
    {
        $source = $run->source;
        $adapter ??= $this->adapterFor($source);
        $scorer = new CandidateScorer(KktcarabamMapper::SUPPORTED_CATEGORIES);
        $counts = array_fill_keys(ImportRun::COUNTERS, 0);
        $report = ['rejections' => [], 'errors' => [], 'rows' => []];

        if ($source->isPaused()) {
            return tap($run)->update(['status' => 'cancelled', 'error' => 'Source is paused.', 'finished_at' => now()]);
        }
        $run->update(['status' => 'running', 'started_at' => now()]);

        try {
            if ($run->mode === 'import' && ! empty($opts['from_run'])) {
                $rows = $this->reconfirm($opts['from_run'], $run, $adapter, $scorer, $counts, $report);
            } else {
                $rows = $this->discoverAndScore($run, $adapter, $scorer, $counts, $report, (int) ($opts['pages'] ?? 4), (int) ($opts['max_fetch'] ?? 90));
                $this->applyFx($rows);
                $rows = $this->choose($rows, $run, $counts, $report);
            }

            if ($run->mode === 'import') {
                foreach ($rows as $sl) {
                    if ($this->paused($source)) {
                        $report['errors'][] = 'Paused by admin during import.';
                        break;
                    }
                    $this->importOne($sl, $adapter, $counts, $report, (bool) ($opts['publish'] ?? false));
                }
            }

            $counts['contacts_pending'] = collect($rows)->filter(fn ($sl) => $sl->contact_publication_status === ImportSource::PENDING)->count();
            $report['rows'] = collect($rows)->map(fn (SourceListing $sl) => [
                'source_listing_id' => $sl->source_listing_id, 'url' => $sl->canonical_url, 'status' => $sl->fresh()->status,
                'title' => trim(($sl->data['model_year'] ?? '').' '.($sl->data['make'] ?? '').' '.($sl->data['model'] ?? '')),
                'price' => ($sl->data['asking_price'] ?? null).' '.($sl->data['currency'] ?? ''),
                'score' => $sl->selection_score, 'listing_id' => $sl->fresh()->listing_id,
            ])->values()->all();
            if (method_exists($adapter, 'contentSignals')) {
                $report['robots_content_signals'] = $adapter->contentSignals();
            }
            $status = collect($report['errors'])->contains(fn ($e) => str_starts_with($e, 'Paused')) ? 'cancelled' : 'completed';
            $run->update($counts + ['status' => $status, 'report' => $report, 'finished_at' => now()]);
        } catch (Throwable $e) {
            Log::error('Import run failed', ['run' => $run->id, 'error' => $e->getMessage()]);
            $counts['errors_count']++;
            $run->update($counts + ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000), 'report' => $report, 'finished_at' => now()]);
        }
        $source->update(['last_run_at' => now()]);

        return $run->fresh();
    }

    /** Re-fetch one listing: refresh activity and price, never overwrite the AIPrice listing. */
    public function recheck(SourceListing $sl, ?SourceAdapter $adapter = null): array
    {
        $adapter ??= $this->adapterFor($sl->source);
        $res = $adapter->fetchListing($sl->canonical_url);
        if ($res['status'] === 'gone') {
            $sl->update(['source_active' => false, 'last_seen_at' => now()]);
            if ($sl->status === 'published') {
                $this->listings->unpublish($sl, 'Listing no longer available at source');
            }

            return ['status' => 'gone'];
        }
        if ($res['status'] !== 'ok') {
            $sl->update(['last_error' => $res['error'], 'attempts' => $sl->attempts + 1]);

            return ['status' => $res['status'], 'error' => $res['error']];
        }
        $new = $res['mapped']['data'];
        $data = $sl->data;
        $changes = [];
        foreach (['asking_price', 'currency', 'mileage'] as $f) {
            if (($data[$f] ?? null) != ($new[$f] ?? null)) {
                $changes[$f] = ['from' => $data[$f] ?? null, 'to' => $new[$f] ?? null];
            }
        }
        if ($changes) {
            $data['source_changes'][] = ['detected_at' => now()->toIso8601String(), 'changes' => $changes];
        }
        $sl->update(['data' => $data, 'source_active' => true, 'last_seen_at' => now(), 'last_error' => null]);

        return ['status' => 'active', 'changes' => $changes];
    }

    /** @return list<SourceListing> rows scored in this run */
    private function discoverAndScore(ImportRun $run, SourceAdapter $adapter, CandidateScorer $scorer, array &$counts, array &$report, int $pages, int $maxFetch): array
    {
        $source = $run->source;
        $found = $adapter->discover($pages);
        $counts['candidates_discovered'] = count($found);
        $run->update(['candidates_discovered' => count($found)]);
        $fingerprints = [];
        $rows = [];

        foreach ($found as $id => $url) {
            if ($counts['candidates_fetched'] >= $maxFetch) {
                break;
            }
            if ($this->paused($source)) {
                $report['errors'][] = 'Paused by admin during discovery.';
                break;
            }
            $canonical = $adapter->canonicalUrl($url);
            $existing = SourceListing::where('import_source_id', $source->id)->where('source_listing_id', (string) $id)->first();
            $key = SourceListing::importKey($source->key, (string) $id);
            if (($existing && $existing->listing_id) || Listing::withTrashed()->where('import_source_key', $key)->exists()) {
                $counts['skipped_duplicates']++;
                $existing?->update(['last_seen_at' => now(), 'last_run_id' => $run->id]);
                $report['rejections']['already_imported'] = ($report['rejections']['already_imported'] ?? 0) + 1;

                continue;
            }

            $res = $adapter->fetchListing($url);
            $counts['candidates_fetched']++;
            if ($res['status'] === 'blocked') {
                throw new RuntimeException($res['error'] ?? 'Source blocked access.');
            }
            if ($res['status'] !== 'ok') {
                $counts['errors_count'] += $res['status'] === 'error' ? 1 : 0;
                $report['errors'][] = "{$id}: {$res['status']} {$res['error']}";
                $existing?->update(['source_active' => $res['status'] !== 'gone', 'last_error' => $res['error'], 'attempts' => $existing->attempts + 1]);

                continue;
            }

            $mapped = $res['mapped'];
            $score = $scorer->score($mapped, $source->contactsAuthorized());
            $reasons = $score['reasons'];
            $fp = CandidateScorer::fingerprint($mapped['data']);
            $dupOf = $fingerprints[$fp] ?? SourceListing::where('import_source_id', $source->id)
                ->where('duplicate_fingerprint', $fp)->where('source_listing_id', '!=', (string) $id)->value('source_listing_id');
            if ($dupOf) {
                $reasons[] = 'duplicate_of:'.$dupOf;
                $counts['skipped_duplicates']++;
            }
            $fingerprints[$fp] ??= (string) $id;

            $sl = $this->upsert($source, $run, (string) $id, $canonical, $mapped, $score, $reasons, $fp);
            foreach ($reasons as $r) {
                $k = explode(':', $r)[0];
                $report['rejections'][$k] = ($report['rejections'][$k] ?? 0) + 1;
            }
            if ($reasons) {
                $counts['rejected'] += $dupOf && count($reasons) === 1 ? 0 : 1;
            } else {
                $counts['eligible']++;
            }
            $rows[] = $sl;
        }

        return $rows;
    }

    private function upsert(ImportSource $source, ImportRun $run, string $id, string $canonical, array $mapped, array $score, array $reasons, string $fp): SourceListing
    {
        $seller = $mapped['seller'];
        $contactsAuthorized = $source->contactsAuthorized();
        // Private individuals' names and any phone are kept only under explicit contact permission.
        $name = ($seller['type'] === 'dealer' || $contactsAuthorized) ? $seller['name'] : null;
        $phone = $contactsAuthorized ? $seller['phone'] : null;
        $e164 = Normalizer::phoneE164($phone);

        return SourceListing::updateOrCreate(
            ['import_source_id' => $source->id, 'source_listing_id' => $id],
            [
                'last_run_id' => $run->id, 'source_url' => $canonical, 'canonical_url' => $canonical,
                'status' => $reasons ? 'rejected' : 'eligible',
                'selection_score' => $score['score'], 'completeness_score' => $score['completeness'],
                'extraction_confidence' => $score['confidence'], 'score_breakdown' => $score['breakdown'],
                'rejection_reasons' => $reasons ?: null, 'data' => $mapped['data'],
                'source_description' => $mapped['description'],
                'content_hash' => hash('sha256', json_encode($mapped['data'])), 'duplicate_fingerprint' => $fp,
                'source_published_at' => $mapped['data']['source_published_at'], 'last_seen_at' => now(), 'source_active' => true,
                'image_publication_status' => $source->imagesAuthorized() ? ImportSource::AUTHORIZED : ImportSource::PENDING,
                'contact_publication_status' => $contactsAuthorized && $e164 ? ImportSource::AUTHORIZED : ImportSource::PENDING,
                'seller_type' => $seller['type'], 'seller_display_name' => $name,
                'seller_phone' => $e164 ? $phone : null, 'seller_phone_e164' => $e164, 'last_error' => null,
            ],
        );
    }

    /**
     * Normalize non-GBP prices with the exchange rates the source itself
     * prints next to each price (X → TL). No external or assumed rates.
     *
     * @param  list<SourceListing>  $rows
     */
    private function applyFx(array $rows): void
    {
        $rates = [];
        foreach ($rows as $sl) {
            $d = $sl->data;
            if ($d['asking_price'] && $d['source_try_equivalent'] && $d['currency'] !== 'TRY') {
                $rates[$d['currency']][] = $d['source_try_equivalent'] / $d['asking_price'];
            }
        }
        $median = function (array $v) {
            sort($v);

            return $v ? $v[intdiv(count($v), 2)] : null;
        };
        $toTry = array_map($median, $rates) + ['TRY' => 1.0];
        foreach ($rows as $sl) {
            $d = $sl->data;
            if ($d['normalized_price_gbp'] === null && $d['asking_price'] && isset($toTry[$d['currency']], $toTry['GBP'])) {
                $d['normalized_price_gbp'] = round($d['asking_price'] * $toTry[$d['currency']] / $toTry['GBP'], 2);
                $d['fx_note'] = 'Converted with GBP/'.$d['currency'].' rates derived from the TL equivalents published by the source on '.now()->toDateString().'.';
                $sl->update(['data' => $d]);
            }
        }
    }

    /** @param list<SourceListing> $rows @return list<SourceListing> */
    private function choose(array $rows, ImportRun $run, array &$counts, array &$report): array
    {
        $eligible = collect($rows)->filter(fn ($sl) => $sl->status === 'eligible');
        $pick = $this->selector->select($eligible->map(fn ($sl) => [
            'key' => $sl->source_listing_id, 'score' => (float) $sl->selection_score, 'data' => $sl->data,
        ])->values()->all(), $run->target);

        $selected = [];
        foreach ($eligible as $sl) {
            if (in_array($sl->source_listing_id, $pick['selected'], true)) {
                $sl->update(['status' => 'selected']);
                $selected[] = $sl;
            } else {
                $sl->update(['score_breakdown' => $sl->score_breakdown + ['not_selected' => $pick['not_selected'][$sl->source_listing_id] ?? 'below_selection_cutoff']]);
            }
        }
        $counts['selected'] = count($selected);
        $report['price_bands'] = $pick['bands'];
        if (count($selected) < 20) {
            $report['errors'][] = 'Only '.count($selected).' eligible listings met the quality rules; fewer than the minimum of 20. Nothing was invented to fill the gap.';
        }

        return $selected;
    }

    /** @return list<SourceListing> */
    private function reconfirm(string $fromRun, ImportRun $run, SourceAdapter $adapter, CandidateScorer $scorer, array &$counts, array &$report): array
    {
        $rows = SourceListing::where('import_source_id', $run->import_source_id)->where('last_run_id', $fromRun)->where('status', 'selected')->get();
        $counts['candidates_discovered'] = $rows->count();
        $confirmed = [];
        foreach ($rows as $sl) {
            $res = $adapter->fetchListing($sl->canonical_url);
            $counts['candidates_fetched']++;
            if ($res['status'] === 'blocked') {
                throw new RuntimeException($res['error'] ?? 'Source blocked access.');
            }
            if ($res['status'] !== 'ok') {
                $sl->update(['status' => $res['status'] === 'gone' ? 'rejected' : 'failed', 'source_active' => $res['status'] !== 'gone',
                    'rejection_reasons' => [$res['status'] === 'gone' ? 'no_longer_active_at_source' : 'fetch_failed'], 'last_error' => $res['error']]);
                $counts[$res['status'] === 'gone' ? 'rejected' : 'errors_count']++;

                continue;
            }
            $score = $scorer->score($res['mapped'], $run->source->contactsAuthorized());
            if (! $score['eligible']) {
                $sl->update(['status' => 'rejected', 'rejection_reasons' => $score['reasons']]);
                $counts['rejected']++;

                continue;
            }
            // Keep the reconfirmed facts (price may have changed since the dry run).
            $data = $res['mapped']['data'] + ['normalized_price_gbp' => $sl->data['normalized_price_gbp'] ?? null];
            if (($data['currency'] ?? null) === 'GBP') {
                $data['normalized_price_gbp'] = $data['asking_price'];
            }
            $sl->update(['data' => $data, 'last_run_id' => $run->id, 'last_seen_at' => now(), 'selection_score' => $score['score']]);
            $counts['eligible']++;
            $confirmed[] = $sl;
        }
        $counts['selected'] = count($confirmed);

        return $confirmed;
    }

    private function importOne(SourceListing $sl, SourceAdapter $adapter, array &$counts, array &$report, bool $publish): void
    {
        try {
            $sl->increment('attempts');
            $img = $this->images->import($sl, $adapter, $sl->data['images'] ?? []);
            $counts['images_stored'] += $img['stored'];
            if ($img['stored'] === 0) {
                throw new RuntimeException('No images could be stored.');
            }
            $result = $this->listings->createDraft($sl->fresh());
            $result['created'] ? $counts['imported']++ : $counts['skipped_duplicates']++;
            $sl->update(['last_error' => null]);
            if ($publish && ! $this->policy->blockers($sl->fresh())) {
                $this->listings->publish($sl->fresh());
            }
        } catch (Throwable $e) {
            $counts['errors_count']++;
            $report['errors'][] = "{$sl->source_listing_id}: ".$e->getMessage();
            $sl->update(['status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 1000)]);
        }
    }

    private function paused(ImportSource $source): bool
    {
        return ImportSource::whereKey($source->id)->value('status') === 'paused';
    }
}
