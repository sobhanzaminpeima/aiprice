<?php

namespace App\Services\Imports\Kktcarabam;

use App\Services\Imports\FeatureDictionary;
use App\Services\Imports\Normalizer;

/**
 * Raw KKTCarabam fields → AIPrice's normalized vehicle record.
 *
 * Every field is either read from the page or null. Conflicts between the
 * page title, the spec table and the description are reported (not resolved
 * by guessing) so the scorer can reject unreliable listings.
 */
final class KktcarabamMapper
{
    public const SUPPORTED_CATEGORIES = ['Otomobil', 'Arazi, SUV, Pick-up', 'Minivan & Panelvan'];

    /**
     * @return array{data: array<string, mixed>, description: ?string, seller: array, conflicts: list<string>, warnings: list<string>}
     */
    public function map(array $raw): array
    {
        $specs = $raw['specs'] ?? [];
        $crumbs = $raw['breadcrumbs'] ?? [];
        $title = (string) ($raw['title'] ?? '');
        $description = $raw['description'] ?? null;
        $conflicts = [];
        $warnings = [];

        $prices = array_values(array_filter(array_map([Normalizer::class, 'price'], $raw['prices'] ?? [])));
        $primary = $prices[0] ?? null;
        $tryEquivalent = null;
        foreach (array_slice($prices, 1) as $p) {
            if ($p['currency'] === 'TRY') {
                $tryEquivalent = $p['amount'];
            }
        }

        $year = Normalizer::year($specs['Yıl'] ?? null);
        $titleYear = Normalizer::year($title);
        if ($year && $titleYear && $year !== $titleYear) {
            $conflicts[] = 'year_title_vs_specs';
        }

        $transmission = Normalizer::transmission($specs['Vites Tipi'] ?? null);
        $titleTransmission = preg_match('/Model\s+(Otomatik|Düz|Yarı Otomatik)\b/u', $title, $m) ? Normalizer::transmission($m[1]) : null;
        if ($transmission && $titleTransmission && $transmission !== $titleTransmission) {
            $conflicts[] = 'transmission_title_vs_specs';
        }

        $mileage = Normalizer::mileage($specs['Kilometre'] ?? null);
        if ($mileage !== null && $description) {
            $mentioned = $this->mileageMentions($description);
            if ($mentioned && $mileage > 1000 && ! array_filter($mentioned, fn ($km) => abs($km - $mileage) <= max(1000, $mileage * 0.1))) {
                $conflicts[] = 'mileage_description_vs_specs';
            }
        }
        if ($year && $description && preg_match_all('/\b(19[5-9]\d|20\d{2})\s*model\b/iu', $description, $ym)) {
            $years = array_unique(array_map('intval', $ym[1]));
            if (! in_array($year, $years, true)) {
                $conflicts[] = 'year_description_vs_specs';
            }
        }

        $make = Normalizer::make($crumbs[2] ?? null);
        $model = isset($crumbs[3]) ? trim($crumbs[3]) : null;
        if ($make && $title !== '' && ! str_contains(Normalizer::lower(str_replace(' ', '', $title)), Normalizer::lower(str_replace([' ', '-'], '', $make)))
            && ! str_contains(Normalizer::lower($title), Normalizer::lower(explode('-', $make)[0]))) {
            $conflicts[] = 'make_title_vs_category';
        }

        $condition = Normalizer::condition($specs['Araç Durumu'] ?? null);
        $engine = Normalizer::intOrNull($specs['Motor Hacmi (cc)'] ?? null, 50, 10000);
        $hp = Normalizer::intOrNull($specs['Motor Gücü (hp)'] ?? null, 20, 2000);
        $featureNames = array_map(fn ($f) => $f['name'], $raw['features'] ?? []);

        $data = [
            'source_website' => 'kktcarabam.com',
            'source_url' => $raw['canonical_url'] ?? null,
            'source_listing_id' => $raw['source_listing_id'] ?? null,
            'original_title' => $title !== '' ? $title : null,
            'category' => $crumbs[1] ?? null,
            'make' => $make,
            'model' => $model,
            'variant' => isset($crumbs[4]) ? trim($crumbs[4]) : null,
            'model_year' => $year,
            'registration_year' => null, // not published by the source
            'asking_price' => $primary['amount'] ?? null,
            'currency' => $primary['currency'] ?? null,
            'source_try_equivalent' => $tryEquivalent,
            'normalized_price_gbp' => ($primary['currency'] ?? null) === 'GBP' ? $primary['amount'] : null,
            'mileage' => $mileage,
            'mileage_unit' => $mileage !== null ? 'km' : null,
            'transmission' => $transmission,
            'fuel_type' => Normalizer::fuel($specs['Yakıt Türü'] ?? null),
            'engine_cc' => $engine,
            'engine_displacement' => $engine ? number_format($engine / 1000, 1).'L' : null,
            'horsepower' => $hp,
            'body_type' => Normalizer::body($specs['Kasa Tipi'] ?? null),
            'exterior_color' => Normalizer::color($specs['Renk'] ?? null),
            'drivetrain' => FeatureDictionary::drivetrain((string) $description, implode("\n", $featureNames)),
            'steering' => Normalizer::steering($specs['Direksiyon Tipi'] ?? null),
            'doors' => Normalizer::intOrNull($specs['Kapı Sayısı'] ?? null, 2, 6),
            'seats' => Normalizer::intOrNull($specs['Koltuk Sayısı'] ?? null, 1, 20),
            'condition' => $condition['condition'],
            'registration_status' => $condition['registration_status'],
            'accident_history' => null,   // only when explicitly declared in a structured field
            'service_history' => null,    // only when explicitly declared in a structured field
            'features' => FeatureDictionary::extract((string) $description, implode("\n", $featureNames)),
            'city' => $raw['address'][0] ?? null,
            'district' => $raw['address'][1] ?? null,
            'seller_type' => ! empty($raw['seller']['profile_url']) ? 'dealer' : (empty($raw['seller']['name']) ? null : 'individual'),
            'source_published_at' => Normalizer::turkishDate($specs['İlan Tarihi'] ?? null),
            'images' => array_values($raw['images'] ?? []),
        ];

        if (($raw['url_id'] ?? null) && ($raw['source_listing_id'] ?? null) && $raw['url_id'] !== $raw['source_listing_id']) {
            $conflicts[] = 'listing_id_url_vs_page';
        }
        if (! empty($raw['seller']['phone_text']) && empty($raw['seller']['phone_fully_visible'])) {
            $warnings[] = 'seller_phone_masked_on_source';
        }

        return [
            'data' => $data,
            'description' => $description,
            'seller' => [
                'name' => $raw['seller']['name'] ?? null,
                'type' => $data['seller_type'],
                // A number is kept only if the page itself prints it in full.
                'phone' => ! empty($raw['seller']['phone_fully_visible']) ? $raw['seller']['phone_text'] : null,
            ],
            'conflicts' => $conflicts,
            'warnings' => $warnings,
        ];
    }

    /** @return list<int> */
    private function mileageMentions(string $text): array
    {
        preg_match_all('/(\d{1,3}(?:[.,]\d{3})+|\d{4,7})\s*(?:km|kilometre)\b/iu', $text, $m);

        return array_values(array_unique(array_filter(array_map(fn ($v) => Normalizer::mileage($v), $m[1]), fn ($v) => $v !== null)));
    }
}
