<?php

namespace Tests\Feature;

use App\Models\CompetitorListing;
use App\Models\CompetitorSource;
use App\Services\CompetitorCrawlerService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketResearchAgentTest extends TestCase
{
    use RefreshDatabase;

    private function source(array $extra = []): CompetitorSource
    {
        return CompetitorSource::create($extra + ['name' => 'Test Market', 'url' => 'https://cars.example-market.test/ilanlar', 'type' => 'vehicle', 'is_active' => true]);
    }

    private function page(): string
    {
        return '<html><head><title>x</title></head><body><nav>menu</nav>'
            .'<div><a href="/ilan/1">2012 VW Passat</a> Girne 10.250 GBP 139.000 km</div>'
            .'<div><a href="/ilan/2">2015 Toyota Corolla</a> Lefkoşa 8,900 GBP</div>'
            .'<div><a href="/ilan/3">Mystery car</a> price on request</div></body></html>';
    }

    private function aiReply(array $listings): array
    {
        return ['choices' => [['message' => ['content' => json_encode(['listings' => $listings])]]]];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['services.openai.key' => 'sk-test', 'services.ai_provider' => 'openai', 'services.openai.base_url' => 'https://ai.test/v1']);
    }

    public function test_keeps_only_prices_that_are_printed_on_the_page(): void
    {
        Http::fake([
            'cars.example-market.test/robots.txt' => Http::response("User-agent: *\nDisallow: /admin", 200),
            'cars.example-market.test/ilanlar' => Http::response($this->page(), 200, ['Content-Type' => 'text/html']),
            'ai.test/*' => Http::response($this->aiReply([
                ['url' => 'https://cars.example-market.test/ilan/1', 'title' => '2012 VW Passat', 'price' => 10250, 'currency' => 'GBP', 'city' => 'Girne', 'year' => 2012, 'mileage_km' => 139000, 'brand' => 'Volkswagen', 'model' => 'Passat'],
                ['url' => 'https://cars.example-market.test/ilan/2', 'title' => '2015 Toyota Corolla', 'price' => '8,900', 'currency' => 'GBP', 'year' => 2015],
                // Invented by the model: this price is not on the page.
                ['url' => 'https://cars.example-market.test/ilan/9', 'title' => 'Ghost car', 'price' => 7777, 'currency' => 'GBP'],
                // Not plausible for a car.
                ['url' => 'https://cars.example-market.test/ilan/3', 'title' => 'Mystery car', 'price' => 5, 'currency' => 'GBP'],
                // Missing currency.
                ['url' => 'https://cars.example-market.test/ilan/1', 'title' => 'No currency', 'price' => 10250],
            ])),
        ]);
        $source = $this->source();

        $result = app(CompetitorCrawlerService::class)->crawlSource($source);

        $this->assertSame(2, $result['imported']);
        $rows = CompetitorListing::where('source_id', $source->id)->get();
        $this->assertEqualsCanonicalizing([10250.0, 8900.0], $rows->pluck('price')->map(fn ($p) => (float) $p)->all());
        $passat = $rows->firstWhere('title', '2012 VW Passat');
        $this->assertSame('https://cars.example-market.test/ilan/1', $passat->url);
        $this->assertSame(139000, $passat->attributes['mileage']);
        $this->assertNotNull($source->fresh()->last_scraped_at);
    }

    public function test_respects_robots_txt(): void
    {
        Http::fake([
            'cars.example-market.test/robots.txt' => Http::response("User-agent: *\nDisallow: /", 200),
            'cars.example-market.test/ilanlar' => Http::response($this->page(), 200, ['Content-Type' => 'text/html']),
            'ai.test/*' => Http::response($this->aiReply([])),
        ]);
        $source = $this->source();

        $result = app(CompetitorCrawlerService::class)->crawlSource($source);

        $this->assertSame(1, $result['errors']);
        $this->assertSame(0, CompetitorListing::count());
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/ilanlar'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'ai.test'));
    }

    public function test_needs_an_ai_key_and_fetches_nothing_without_one(): void
    {
        config(['services.openai.key' => '']);
        Http::fake();

        $result = app(CompetitorCrawlerService::class)->crawlSource($this->source());

        $this->assertSame(1, $result['errors']);
        Http::assertNothingSent();
    }

    public function test_refuses_private_addresses(): void
    {
        Http::fake();
        $result = app(CompetitorCrawlerService::class)->crawlSource($this->source(['url' => 'http://127.0.0.1/ilanlar']));
        $this->assertSame(1, $result['errors']);
        Http::assertNothingSent();
    }

    public function test_listings_the_site_stopped_showing_are_retired(): void
    {
        Http::fake([
            'cars.example-market.test/robots.txt' => Http::response('', 404),
            'cars.example-market.test/ilanlar' => Http::response($this->page(), 200, ['Content-Type' => 'text/html']),
            'ai.test/*' => Http::response($this->aiReply([['url' => 'https://cars.example-market.test/ilan/1', 'title' => 'VW', 'price' => 10250, 'currency' => 'GBP']])),
        ]);
        $source = $this->source();
        $old = CompetitorListing::create(['source_id' => $source->id, 'external_id' => 'ai-old', 'type' => 'vehicle', 'title' => 'Sold car', 'price' => 5000, 'currency' => 'GBP', 'is_active' => true]);
        CompetitorListing::whereKey($old->id)->update(['updated_at' => now()->subDays(20)]);

        app(CompetitorCrawlerService::class)->crawlSource($source);

        $this->assertFalse((bool) $old->fresh()->is_active);
        $this->assertSame(1, CompetitorListing::where('source_id', $source->id)->where('is_active', true)->count());
    }
}
