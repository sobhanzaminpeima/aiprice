<?php

namespace App\Services\Imports;

use Illuminate\Support\Carbon;

/**
 * Deterministic, explainable eligibility and quality score for one candidate.
 *
 * Score (0–100) = completeness 30 + photos 20 + price clarity 15 + identity 15
 *               + freshness 10 + description 5 + authorized contact 5.
 *
 * A low price never raises the score: price only has to be present and
 * plausible. Hard rejections are listed in `reasons` and make it ineligible.
 */
final class CandidateScorer
{
    public const MIN_IMAGES = 4;

    /** Plausible asking-price window per currency (inclusive). */
    private const PRICE_RANGE = [
        'GBP' => [500, 400_000], 'EUR' => [600, 450_000], 'USD' => [600, 500_000], 'TRY' => [20_000, 25_000_000],
    ];

    private const COMPLETENESS_FIELDS = [
        'make', 'model', 'model_year', 'mileage', 'transmission', 'fuel_type', 'body_type',
        'exterior_color', 'engine_cc', 'steering', 'horsepower', 'city',
    ];

    public function __construct(private array $supportedCategories = []) {}

    /**
     * @param  array{data: array, description: ?string, conflicts: list<string>, seller: array}  $mapped
     * @return array{eligible: bool, score: float, completeness: int, confidence: int, breakdown: array, reasons: list<string>}
     */
    public function score(array $mapped, bool $contactAuthorized = false, ?Carbon $now = null): array
    {
        $d = $mapped['data'];
        $now ??= now();
        $reasons = [];

        if ($this->supportedCategories && ! in_array($d['category'] ?? null, $this->supportedCategories, true)) {
            $reasons[] = 'unsupported_category';
        }
        if (! $d['make'] || ! $d['model'] || ! $d['model_year']) {
            $reasons[] = 'missing_core_identity';
        }
        if ($d['condition'] === 'new') {
            $reasons[] = 'not_a_used_vehicle';
        }
        if ($d['mileage'] === null || $d['mileage'] <= 0) {
            $reasons[] = 'missing_mileage';
        } elseif ($d['mileage'] > 600_000) {
            $reasons[] = 'implausible_mileage';
        }
        if ($d['asking_price'] === null || $d['currency'] === null) {
            $reasons[] = 'missing_price';
        } else {
            [$min, $max] = self::PRICE_RANGE[$d['currency']] ?? [null, null];
            if ($min === null || $d['asking_price'] < $min || $d['asking_price'] > $max) {
                $reasons[] = 'implausible_price';
            }
        }
        if (count($d['images'] ?? []) < self::MIN_IMAGES) {
            $reasons[] = 'insufficient_images';
        }
        foreach ($mapped['conflicts'] ?? [] as $conflict) {
            $reasons[] = 'conflict:'.$conflict;
        }

        $present = count(array_filter(self::COMPLETENESS_FIELDS, fn ($f) => ($d[$f] ?? null) !== null && $d[$f] !== ''));
        $completeness = (int) round($present / count(self::COMPLETENESS_FIELDS) * 100);

        $age = $d['source_published_at'] ? Carbon::parse($d['source_published_at'])->diffInDays($now, false) : null;
        $freshness = match (true) {
            $age === null => 2,
            $age <= 14 => 10,
            $age <= 45 => 8,
            $age <= 120 => 5,
            $age <= 365 => 3,
            default => 1,
        };

        $breakdown = [
            'completeness' => round($completeness * 0.30, 2),
            'photos' => min(count($d['images'] ?? []), 10) * 2,
            'price_clarity' => in_array('missing_price', $reasons, true) || in_array('implausible_price', $reasons, true) ? 0 : 15,
            'identity' => ($d['make'] && $d['model'] ? 5 : 0) + ($d['model_year'] ? 5 : 0) + (($d['mileage'] ?? 0) > 0 ? 5 : 0),
            'freshness' => $freshness,
            'description' => min(5, intdiv(mb_strlen((string) ($mapped['description'] ?? '')), 60)),
            'authorized_contact' => $contactAuthorized && ! empty($mapped['seller']['phone']) ? 5 : 0,
        ];

        $confidence = max(0, 100 - 25 * count($mapped['conflicts'] ?? []) - (100 - $completeness) / 4);

        return [
            'eligible' => $reasons === [],
            'score' => round(array_sum($breakdown), 2),
            'completeness' => $completeness,
            'confidence' => (int) round($confidence),
            'breakdown' => $breakdown,
            'reasons' => $reasons,
        ];
    }

    /** Same vehicle advertised twice: make/model/year/mileage/price. */
    public static function fingerprint(array $d): string
    {
        return hash('sha256', implode('|', [
            Normalizer::lower((string) $d['make']), Normalizer::lower((string) $d['model']), $d['model_year'],
            $d['mileage'], $d['asking_price'], $d['currency'],
        ]));
    }
}
