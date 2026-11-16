<?php

namespace App\Services\Imports;

use App\Services\Imports\Contracts\SourceAdapter;
use App\Services\Imports\Kktcarabam\KktcarabamParser;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Reads an export the source operator supplied (JSON manifest + local image
 * directory) and feeds it through the same scoring/selection/media/draft
 * pipeline as live pages. Used when public pages are protected against
 * automated access.
 *
 * Manifest: {"listings": [{
 *   "source_listing_id": "272325", "source_url": "https://www.kktcarabam.com/272325-…",
 *   "category": "Otomobil", "make": "Toyota", "model": "Yaris Cross", "variant": null,
 *   "year": 2022, "price": 22300, "currency": "GBP", "try_equivalent": 1488449.18,
 *   "mileage": 18500, "fuel_type": "Hibrit", "transmission": "Otomatik", "body_type": "Crossover",
 *   "color": "Beyaz", "engine_cc": 1500, "horsepower": 140, "steering": "Sağ Direksiyon",
 *   "condition": "2.El (Plakasız)", "city": "Güzelyurt", "district": "Güzelyurt Merkez",
 *   "published_at": "2026-11-16", "description": "…",
 *   "seller": {"type": "dealer|individual", "name": "…", "phone": "+90 533 …"},
 *   "images": ["272325/01.jpg", "272325/02.jpg"]
 * }]}
 */
class ExportFeedAdapter implements SourceAdapter
{
    /** @var array<string, array> */
    private array $records = [];

    private string $imageRoot;

    public function __construct(string $manifestPath, string $imageRoot)
    {
        if (! is_file($manifestPath)) {
            throw new InvalidArgumentException('Feed manifest not found.');
        }
        $root = realpath($imageRoot);
        if ($root === false || ! is_dir($root)) {
            throw new InvalidArgumentException('Feed image directory not found.');
        }
        $this->imageRoot = $root;
        try {
            $json = json_decode((string) file_get_contents($manifestPath), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Feed manifest is not valid JSON: '.$e->getMessage());
        }
        foreach (array_slice($json['listings'] ?? [], 0, 500) as $row) {
            $id = (string) ($row['source_listing_id'] ?? '');
            if (preg_match('/^\d{3,}$/', $id) && KktcarabamParser::idFromUrl((string) ($row['source_url'] ?? '')) === $id) {
                $this->records[$id] = $row;
            }
        }
    }

    public function key(): string
    {
        return 'kktcarabam';
    }

    public function discover(int $maxPages): array
    {
        return array_map(fn ($r) => KktcarabamParser::canonicalUrl($r['source_url']), $this->records);
    }

    public function idFromUrl(string $url): ?string
    {
        return KktcarabamParser::idFromUrl($url);
    }

    public function canonicalUrl(string $url): ?string
    {
        return KktcarabamParser::canonicalUrl($url);
    }

    public function fetchListing(string $url): array
    {
        $id = $this->idFromUrl($url);
        $r = $id ? ($this->records[$id] ?? null) : null;
        if (! $r) {
            return ['status' => 'gone', 'http_status' => null, 'mapped' => null, 'error' => null];
        }
        $known = fn (?string $v, callable $map, array $normalized) => $v === null ? null : ($map($v) ?? (in_array($v, $normalized, true) ? $v : null));
        $price = is_numeric($r['price'] ?? null) ? (float) $r['price'] : null;
        $currency = Normalizer::CURRENCIES[strtoupper((string) ($r['currency'] ?? ''))] ?? null;
        $cond = Normalizer::condition($r['condition'] ?? '2.El');
        $desc = isset($r['description']) ? (string) $r['description'] : null;
        $engine = Normalizer::intOrNull($r['engine_cc'] ?? null, 50, 10000);

        $data = [
            'source_website' => 'kktcarabam.com', 'source_url' => KktcarabamParser::canonicalUrl($r['source_url']),
            'source_listing_id' => $id, 'original_title' => $r['title'] ?? null, 'category' => $r['category'] ?? null,
            'make' => Normalizer::make($r['make'] ?? null), 'model' => isset($r['model']) ? trim($r['model']) : null,
            'variant' => $r['variant'] ?? null, 'model_year' => Normalizer::year($r['year'] ?? null), 'registration_year' => null,
            'asking_price' => $price && $price > 0 ? $price : null, 'currency' => $currency,
            'source_try_equivalent' => is_numeric($r['try_equivalent'] ?? null) ? (float) $r['try_equivalent'] : null,
            'normalized_price_gbp' => $currency === 'GBP' ? $price : null,
            'mileage' => Normalizer::mileage($r['mileage'] ?? null), 'mileage_unit' => isset($r['mileage']) ? 'km' : null,
            'transmission' => $known($r['transmission'] ?? null, [Normalizer::class, 'transmission'], ['automatic', 'manual', 'semi_automatic', 'cvt']),
            'fuel_type' => $known($r['fuel_type'] ?? null, [Normalizer::class, 'fuel'], ['petrol', 'diesel', 'hybrid', 'electric', 'lpg', 'petrol_lpg', 'plug_in_hybrid']),
            'engine_cc' => $engine, 'engine_displacement' => $engine ? number_format($engine / 1000, 1).'L' : null,
            'horsepower' => Normalizer::intOrNull($r['horsepower'] ?? null, 20, 2000),
            'body_type' => $known($r['body_type'] ?? null, [Normalizer::class, 'body'], ['sedan', 'hatchback', 'crossover', 'suv', 'estate', 'coupe', 'convertible', 'mpv', 'pickup', 'van']),
            'exterior_color' => Normalizer::color($r['color'] ?? null),
            'drivetrain' => FeatureDictionary::drivetrain((string) $desc),
            'steering' => $known($r['steering'] ?? null, [Normalizer::class, 'steering'], ['left', 'right']),
            'doors' => Normalizer::intOrNull($r['doors'] ?? null, 2, 6), 'seats' => Normalizer::intOrNull($r['seats'] ?? null, 1, 20),
            'condition' => $cond['condition'], 'registration_status' => $cond['registration_status'],
            'accident_history' => null, 'service_history' => null,
            'features' => FeatureDictionary::extract((string) $desc),
            'city' => $r['city'] ?? null, 'district' => $r['district'] ?? null,
            'seller_type' => in_array($r['seller']['type'] ?? null, ['dealer', 'individual'], true) ? $r['seller']['type'] : null,
            'source_published_at' => isset($r['published_at']) && strtotime((string) $r['published_at']) ? date('Y-m-d', strtotime((string) $r['published_at'])) : null,
            'images' => array_values(array_map(fn ($f) => 'feed://'.ltrim((string) $f, '/'), array_filter((array) ($r['images'] ?? []), 'is_string'))),
        ];

        return ['status' => 'ok', 'http_status' => null, 'error' => null, 'mapped' => [
            'data' => $data, 'description' => $desc,
            'seller' => ['name' => $r['seller']['name'] ?? null, 'type' => $data['seller_type'], 'phone' => $r['seller']['phone'] ?? null],
            'conflicts' => [], 'warnings' => [],
        ]];
    }

    public function downloadImage(string $url): string
    {
        if (! str_starts_with($url, 'feed://')) {
            throw new RuntimeException('Feed images must be files from the supplied image directory.');
        }
        $path = realpath($this->imageRoot.DIRECTORY_SEPARATOR.substr($url, 7));
        if ($path === false || ! str_starts_with($path, $this->imageRoot.DIRECTORY_SEPARATOR) || ! is_file($path)) {
            throw new RuntimeException('Feed image is missing or outside the image directory.');
        }

        return (string) file_get_contents($path);
    }
}
