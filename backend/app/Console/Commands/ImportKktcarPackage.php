<?php

namespace App\Console\Commands;

use App\Services\Imports\KktcarPackageImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * php artisan imports:kktcar-package <package-dir> --dry-run
 * php artisan imports:kktcar-package <package-dir> --report-dir=storage/app/private/import-reports
 *
 * Creates DRAFT listings only. Never publishes, never stores contacts or images,
 * never queues price analysis.
 */
class ImportKktcarPackage extends Command
{
    protected $signature = 'imports:kktcar-package {dir : Path to the AIPRICE_KKTCAR_IMPORT folder}
        {--dry-run : Validate and check duplicates without writing listings}
        {--report-dir= : Write JSON + Markdown reports here}';

    protected $description = 'Import the KKTCar export package as draft listings (rights clearance required)';

    public function handle(KktcarPackageImporter $importer): int
    {
        try {
            $res = $importer->import((string) $this->argument('dir'), (bool) $this->option('dry-run'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $run = $res['run'];
        $this->table(['metric', 'count'], [
            ['records in package', $run->candidates_discovered],
            [$this->option('dry-run') ? 'would be created (draft)' : 'created (draft)', count($res['created'])],
            ['skipped (duplicate)', count($res['skipped'])],
            ['rejected (validation)', count($res['rejected'])],
            ['flagged (needs manual review)', count($res['flagged'])],
            ['blocked by rights (clearance required)', $this->option('dry-run') ? 0 : count($res['created'])],
            ['images stored', 0],
            ['seller contacts stored', 0],
            ['reconciliation mismatches', count($res['reconciliation']['mismatches'] ?? [])],
        ]);
        foreach ([...$res['skipped'], ...$res['rejected']] as $row) {
            $this->line(json_encode($row, JSON_UNESCAPED_UNICODE));
        }
        foreach ($res['warnings'] as $w) {
            $this->warn($w);
        }

        if ($dir = $this->option('report-dir')) {
            @mkdir($dir, 0775, true);
            $base = rtrim($dir, '/\\').'/kktcar-package-'.$run->mode.'-'.now()->format('Ymd-His');
            file_put_contents("$base.json", json_encode(array_merge($res, ['run' => $run->toArray()]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            file_put_contents("$base.md", $this->markdown($res));
            $this->line("Reports: $base.json / .md");
        }

        return self::SUCCESS;
    }

    private function markdown(array $res): string
    {
        $run = $res['run'];
        $lines = ["# KKTCar package import ({$run->mode})", '', 'Run: '.$run->id.' · '.$run->finished_at?->toIso8601String(), '',
            '| Item | Count |', '|---|---|',
            '| Records in package | '.$run->candidates_discovered.' |',
            '| Created as draft | '.count($res['created']).' |',
            '| Skipped (duplicate) | '.count($res['skipped']).' |',
            '| Rejected (validation) | '.count($res['rejected']).' |',
            '| Flagged — needs manual review | '.count($res['flagged']).' |',
            '| Blocked by rights (clearance required) | '.($run->mode === 'import' ? count($res['created']) : 0).' |',
            '| Images stored / contacts stored | 0 / 0 |',
            '| Reconciliation (price, currency, year, mileage) | '.($res['reconciliation']['checked'] ?? 0).' checked, '.count($res['reconciliation']['mismatches'] ?? []).' mismatches |',
            '', '## Flagged', ''];
        foreach ($res['flagged'] as $f) {
            $lines[] = "- {$f['external_id']}: ".implode('; ', $f['flags']);
        }
        $lines[] = '';
        $lines[] = '## Created';
        $lines[] = '';
        foreach ($res['created'] as $c) {
            $lines[] = "- {$c['external_id']}: {$c['title']} → /listings/{$c['slug']}".(! empty($c['needs_review']) ? ' (needs review)' : '');
        }
        foreach (['skipped' => 'Skipped', 'rejected' => 'Rejected'] as $k => $h) {
            if ($res[$k]) {
                $lines[] = '';
                $lines[] = "## $h";
                foreach ($res[$k] as $row) {
                    $lines[] = '- '.json_encode($row, JSON_UNESCAPED_UNICODE);
                }
            }
        }

        return implode("\n", $lines)."\n";
    }
}
