<?php

namespace App\Services\Imports;

/**
 * Picks a diverse, high-quality subset of eligible candidates.
 *
 * Greedy by score (ties broken by source ID for determinism) with caps per
 * make, per make+model and per GBP price band. If the capped pass falls short
 * of the minimum, the model and band caps are relaxed — but never beyond the
 * eligible pool: listings are never invented to reach a target.
 */
final class CandidateSelector
{
    public const BANDS = [[0, 5000, '<5k'], [5000, 10000, '5–10k'], [10000, 20000, '10–20k'], [20000, 40000, '20–40k'], [40000, PHP_INT_MAX, '40k+']];

    /**
     * @param  list<array{key: string, score: float, data: array}>  $candidates
     * @return array{selected: list<string>, not_selected: array<string, string>, bands: array<string, int>}
     */
    public function select(array $candidates, int $target = 35, int $min = 20, int $max = 50): array
    {
        $target = max($min, min($max, $target));
        usort($candidates, fn ($a, $b) => [$b['score'], $a['key']] <=> [$a['score'], $b['key']]);

        $caps = [
            'make' => max(3, (int) ceil($target * 0.15)),
            'model' => 2,
            'band' => (int) ceil($target * 0.35),
        ];

        $selected = [];
        $counts = ['make' => [], 'model' => [], 'band' => []];
        $notSelected = [];

        foreach ([1, 2] as $pass) {
            foreach ($candidates as $c) {
                if (count($selected) >= $target) {
                    break 2;
                }
                if (isset($selected[$c['key']])) {
                    continue;
                }
                $make = Normalizer::lower((string) $c['data']['make']);
                $model = $make.'|'.Normalizer::lower((string) $c['data']['model']);
                $band = self::band($c['data']['normalized_price_gbp'] ?? null);

                $over = match (true) {
                    ($counts['make'][$make] ?? 0) >= $caps['make'] => 'diversity_cap_make',
                    $pass === 1 && ($counts['model'][$model] ?? 0) >= $caps['model'] => 'diversity_cap_model',
                    $pass === 1 && ($counts['band'][$band] ?? 0) >= $caps['band'] => 'diversity_cap_price_band',
                    default => null,
                };
                if ($over) {
                    $notSelected[$c['key']] = $over;

                    continue;
                }
                $selected[$c['key']] = true;
                unset($notSelected[$c['key']]);
                $counts['make'][$make] = ($counts['make'][$make] ?? 0) + 1;
                $counts['model'][$model] = ($counts['model'][$model] ?? 0) + 1;
                $counts['band'][$band] = ($counts['band'][$band] ?? 0) + 1;
            }
            if (count($selected) >= $min) {
                break;
            }
        }

        foreach ($candidates as $c) {
            if (! isset($selected[$c['key']]) && ! isset($notSelected[$c['key']])) {
                $notSelected[$c['key']] = 'below_selection_cutoff';
            }
        }

        // Numeric-string keys ("272325") become ints in PHP arrays; hand back strings.
        return ['selected' => array_map('strval', array_keys($selected)), 'not_selected' => $notSelected, 'bands' => $counts['band']];
    }

    public static function band(?float $gbp): string
    {
        if ($gbp === null) {
            return 'unknown';
        }
        foreach (self::BANDS as [$lo, $hi, $label]) {
            if ($gbp >= $lo && $gbp < $hi) {
                return $label;
            }
        }

        return 'unknown';
    }
}
