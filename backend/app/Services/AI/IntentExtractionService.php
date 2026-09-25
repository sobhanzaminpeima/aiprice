<?php

namespace App\Services\AI;

use App\Models\Listing;
use Illuminate\Support\Facades\Cache;

/**
 * Converts free-text search messages into a structured intent payload.
 * Deterministic keyword/regex extraction — no LLM round-trip required,
 * so search stays fast and never depends on an external API being up.
 */
class IntentExtractionService
{
    private const TYPE_KEYWORDS = [
        'vehicle' => ['car', 'cars', 'vehicle', 'suv', 'sedan', 'auto', 'automobile', 'truck', 'van', 'bmw', 'toyota', 'mercedes', 'honda', 'volkswagen'],
        'property' => ['property', 'properties', 'apartment', 'apt', 'flat', 'villa', 'house', 'home', 'land'],
        'rental' => ['rental', 'rent', 'renting', 'lease'],
    ];

    public function extract(string $message): array
    {
        $lower = mb_strtolower($message);

        return [
            'raw' => $message,
            'asset_type' => $this->detectType($lower),
            'budget_max' => $this->detectBudget($lower),
            'city' => $this->detectCity($lower),
        ];
    }

    private function detectType(string $lower): ?string
    {
        foreach (self::TYPE_KEYWORDS as $type => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    return $type;
                }
            }
        }

        return null;
    }

    private function detectBudget(string $lower): ?float
    {
        // Requires an explicit budget signal (keyword, currency symbol, or k/thousand
        // suffix) — a bare number like "2020 Toyota" must never be read as a price.
        $keyword = '(?:under|below|up to|max(?:imum)?|budget(?:\s*of)?)';
        $currency = '[£$€]';
        $suffix = '(?:k|thousand)';

        $patterns = [
            // "under 20000", "budget of 300,000", "max 20k"
            "/{$keyword}\\s*{$currency}?\\s*([\\d,]+(?:\\.\\d+)?)\\s*({$suffix})?/i",
            // "£20,000", "$300k" without a leading keyword
            "/{$currency}\\s*([\\d,]+(?:\\.\\d+)?)\\s*({$suffix})?/i",
            // "20k budget", "300 thousand"
            "/([\\d,]+(?:\\.\\d+)?)\\s*{$suffix}/i",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $lower, $m)) {
                $value = (float) str_replace(',', '', $m[1]);
                if ($value <= 0) {
                    continue;
                }
                if (!empty($m[2] ?? null)) {
                    $value *= 1000;
                }

                return $value;
            }
        }

        return null;
    }

    private function detectCity(string $lower): ?string
    {
        $cities = Cache::remember('chat.known_cities', 3600, function () {
            return Listing::whereNotNull('city')->distinct()->pluck('city')->filter()->values()->all();
        });

        foreach ($cities as $city) {
            if ($city && str_contains($lower, mb_strtolower($city))) {
                return $city;
            }
        }

        return null;
    }
}
