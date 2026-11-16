<?php

namespace Tests\Unit\Imports;

use App\Services\Imports\CandidateScorer;
use App\Services\Imports\CandidateSelector;
use App\Services\Imports\Kktcarabam\KktcarabamMapper;
use App\Services\Imports\Kktcarabam\KktcarabamParser;
use App\Services\Imports\Normalizer;
use App\Services\Imports\RobotsPolicy;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\KktFixture;

class NormalizationAndParsingTest extends TestCase
{
    public static function prices(): array
    {
        return [
            ['22.300 GBP', 22300.0, 'GBP'], ['258.000 TL', 258000.0, 'TRY'], ['1.488.449,18 TL', 1488449.18, 'TRY'],
            ['€ 9.500', 9500.0, 'EUR'], ['15,000 USD', 15000.0, 'USD'], ['0 TL', null, null], ['Fiyat sorunuz', null, null], ['', null, null],
        ];
    }

    #[DataProvider('prices')]
    public function test_price_and_currency_normalization(string $in, ?float $amount, ?string $currency): void
    {
        $p = Normalizer::price($in);
        $this->assertSame($amount, $p['amount'] ?? null);
        $this->assertSame($currency, $p['currency'] ?? null);
    }

    public function test_year_and_mileage_normalization(): void
    {
        $this->assertSame(2022, Normalizer::year('2022'));
        $this->assertSame(2019, Normalizer::year('2019 Model Otomatik'));
        $this->assertNull(Normalizer::year('2099', 2026));
        $this->assertNull(Normalizer::year('model yok'));
        $this->assertSame(18500, Normalizer::mileage('18500'));
        $this->assertSame(18500, Normalizer::mileage('18.500 km'));
        $this->assertSame(16093, Normalizer::mileage('10,000 miles'));
        $this->assertNull(Normalizer::mileage(''));
        $this->assertNull(Normalizer::mileage('bilinmiyor'));
    }

    public function test_attribute_maps_return_null_for_unknown_values(): void
    {
        $this->assertSame('hybrid', Normalizer::fuel('Hibrit'));
        $this->assertSame('petrol_lpg', Normalizer::fuel('Benzin & LPG'));
        $this->assertNull(Normalizer::fuel('Hidrojen?'));
        $this->assertSame('semi_automatic', Normalizer::transmission('Yarı Otomatik'));
        $this->assertSame('manual', Normalizer::transmission('Düz'));
        $this->assertSame('right', Normalizer::steering('Sağ Direksiyon'));
        $this->assertSame('2026-11-16', Normalizer::turkishDate('16 Kasım 2026'));
        $this->assertNull(Normalizer::turkishDate('31 Şubat 2026'));
        $this->assertSame(['condition' => 'used', 'registration_status' => 'unregistered'], Normalizer::condition('2.El (Plakasız)'));
        $this->assertSame('Mercedes-Benz', Normalizer::make('Mercedes - Benz'));
    }

    public function test_phone_normalization_never_completes_masked_numbers(): void
    {
        $this->assertNull(Normalizer::phoneE164('0533 *** ** **'));
        $this->assertNull(Normalizer::phoneE164('0533 8•• ••••'));
        $this->assertNull(Normalizer::phoneE164('533 12'));
        $this->assertSame('+905338123456', Normalizer::phoneE164('0533 812 34 56'));
        $this->assertSame('+903922252930', Normalizer::phoneE164('+90 392 225 2930'));
    }

    public function test_parser_reads_listing_fields_and_ignores_sidebar_prices(): void
    {
        $html = KktFixture::listingHtml(['id' => '123456', 'variant' => 'E 200', 'images' => 3]);
        $raw = (new KktcarabamParser)->parseListing($html, 'https://www.kktcarabam.com/123456-toyota-corolla-lefkosa');
        $this->assertSame('123456', $raw['source_listing_id']);
        $this->assertSame(['12.500 GBP', '834.000,00 TL'], $raw['prices']);
        $this->assertCount(3, $raw['images']);
        $this->assertStringEndsWith('-1-900_675.jpg', $raw['images'][0]);
        $this->assertSame(['Lefkoşa', 'Gönyeli'], $raw['address']);
        $this->assertFalse($raw['seller']['phone_fully_visible']);

        $m = (new KktcarabamMapper)->map($raw);
        $d = $m['data'];
        $this->assertSame(['Toyota', 'Corolla', 'E 200', 2020, 12500.0, 'GBP', 45000, 'automatic', 'petrol', 'right', 'sedan', 'White'],
            [$d['make'], $d['model'], $d['variant'], $d['model_year'], $d['asking_price'], $d['currency'], $d['mileage'],
                $d['transmission'], $d['fuel_type'], $d['steering'], $d['body_type'], $d['exterior_color']]);
        $this->assertNull($d['accident_history']);
        $this->assertNull($d['service_history']);
        $this->assertNull($d['registration_year']);
        $this->assertContains('Reversing camera', $d['features']);
        $this->assertContains('Heated seats', $d['features']);
        $this->assertNull($m['seller']['phone'], 'Masked phone must never be stored');
        $this->assertContains('seller_phone_masked_on_source', $m['warnings']);
        $this->assertSame([], $m['conflicts']);
    }

    public function test_index_parser_skips_new_vehicle_links(): void
    {
        $html = '<a href="https://www.kktcarabam.com/272325-toyota-yaris-cross-guzelyurt">x</a>'
            .'<a href="https://www.kktcarabam.com/174113-sifir-km-mercedes-benz-a-serisi-lefkosa">x</a>'
            .'<a href="https://www.kktcarabam.com/272325-toyota-yaris-cross-guzelyurt">dup</a>';
        $this->assertSame(['272325' => 'https://www.kktcarabam.com/272325-toyota-yaris-cross-guzelyurt'], (new KktcarabamParser)->parseIndex($html));
        $this->assertNull(KktcarabamParser::idFromUrl('https://evil.example/272325-toyota'));
    }

    private function mapped(array $o): array
    {
        $html = KktFixture::listingHtml($o);

        return (new KktcarabamMapper)->map((new KktcarabamParser)->parseListing($html, 'https://www.kktcarabam.com/'.($o['id'] ?? '900001').'-x'));
    }

    public function test_invalid_or_missing_fields_and_conflicts_are_rejected(): void
    {
        $scorer = new CandidateScorer(KktcarabamMapper::SUPPORTED_CATEGORIES);
        $now = Carbon::parse('2026-11-16');
        $cases = [
            'missing_price' => ['price' => null],
            'implausible_price' => ['price' => '1 GBP', 'try' => null],
            'missing_mileage' => ['km' => null],
            'insufficient_images' => ['images' => 2],
            'not_a_used_vehicle' => ['condition' => 'Sıfır'],
            'unsupported_category' => ['category' => 'Motosiklet'],
            'conflict:year_title_vs_specs' => ['title_year' => 2018],
            'conflict:transmission_title_vs_specs' => ['title_gear' => 'Düz'],
            'conflict:mileage_description_vs_specs' => ['description' => ['Sadece 120.000 km de']],
        ];
        foreach ($cases as $reason => $override) {
            $r = $scorer->score($this->mapped($override), false, $now);
            $this->assertFalse($r['eligible'], $reason);
            $this->assertContains($reason, $r['reasons'], $reason);
        }
        $ok = $scorer->score($this->mapped([]), false, $now);
        $this->assertTrue($ok['eligible']);
        $this->assertSame(100, $ok['completeness']);
    }

    public function test_low_price_does_not_raise_the_score(): void
    {
        $scorer = new CandidateScorer;
        $now = Carbon::parse('2026-11-16');
        $cheap = $scorer->score($this->mapped(['price' => '3.000 GBP']), false, $now);
        $dear = $scorer->score($this->mapped(['price' => '30.000 GBP']), false, $now);
        $this->assertSame($cheap['score'], $dear['score']);
    }

    public function test_duplicate_fingerprint_and_deterministic_diverse_selection(): void
    {
        $a = $this->mapped(['id' => '111111'])['data'];
        $b = $this->mapped(['id' => '222222'])['data'];
        $this->assertSame(CandidateScorer::fingerprint($a), CandidateScorer::fingerprint($b));

        $cands = [];
        foreach (range(1, 30) as $i) {
            $cands[] = ['key' => (string) $i, 'score' => 80.0, 'data' => ['make' => $i <= 12 ? 'Toyota' : "Make{$i}", 'model' => $i <= 12 ? 'Corolla' : "M{$i}", 'normalized_price_gbp' => 5000 + $i * 900]];
        }
        $sel = new CandidateSelector;
        $first = $sel->select($cands, 20);
        $this->assertSame($first, $sel->select(array_reverse($cands), 20), 'selection must be deterministic');
        $this->assertCount(20, $first['selected']);
        $toyotas = array_filter($first['selected'], fn ($k) => (int) $k <= 12);
        $this->assertLessThanOrEqual(3, count($toyotas));
        $this->assertSame('diversity_cap_make', $first['not_selected']['12'] ?? $first['not_selected']['11']);

        // Never more than the eligible pool, even below the minimum.
        $small = $sel->select(array_slice($cands, 12, 5), 35);
        $this->assertCount(5, $small['selected']);
    }

    public function test_robots_policy(): void
    {
        $txt = "User-agent: *\nContent-Signal: search=yes,ai-train=no\nAllow: /\n\nUser-agent: GPTBot\nDisallow: /\n\nUser-agent: AIPriceListingImporter\nDisallow: /private/\n";
        $p = RobotsPolicy::parse($txt, 'AIPriceListingImporter');
        $this->assertTrue($p->allows('/272325-toyota'));
        $this->assertFalse($p->allows('/private/x'));
        $this->assertSame('no', $p->contentSignals['ai-train']);
        $this->assertFalse(RobotsPolicy::parse($txt, 'GPTBot')->allows('/anything'));
        $this->assertFalse(RobotsPolicy::parse("User-agent: *\nDisallow: /\n", 'AIPriceListingImporter')->allows('/x'));
    }
}
