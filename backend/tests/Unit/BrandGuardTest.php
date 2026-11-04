<?php

namespace Tests\Unit;

use App\Support\BrandGuard;
use PHPUnit\Framework\TestCase;

class BrandGuardTest extends TestCase
{
    public function test_strips_imported_source_urls(): void
    {
        $out = BrandGuard::scrub('2019 BMW 320i in Girne. Source: https://www.kktcarabam.com/123456-bmw');
        $this->assertSame('2019 BMW 320i in Girne.', $out);
    }

    public function test_replaces_third_party_names_in_latin_and_persian(): void
    {
        $this->assertSame('Listed on AIPrice.app today', BrandGuard::scrub('Listed on KKTC Arabam today'));
        $this->assertSame('آگهی در AIPrice.app', BrandGuard::scrub('آگهی در کاکاتج عربا'));
        $this->assertStringNotContainsStringIgnoringCase('kktcarabam', BrandGuard::scrub('See kktcarabam.com for more'));
    }

    public function test_removes_overclaiming_price_wording(): void
    {
        $this->assertSame('AI price estimate', BrandGuard::scrub('AI-verified fair price'));
    }

    public function test_prompt_rules_name_the_brand(): void
    {
        $this->assertStringContainsString('AIPrice.app', BrandGuard::PROMPT_RULES);
    }
}
