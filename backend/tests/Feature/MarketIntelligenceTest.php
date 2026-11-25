<?php

namespace Tests\Feature;

use App\Jobs\TranslateListing;
use App\Models\FraudReport;
use App\Models\Listing;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Services\AI\ScoringOrchestratorService;
use App\Services\Market\RiskAssessment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private VehicleModel $model;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $brand = VehicleBrand::firstOrCreate(['name' => 'Ford'], ['is_active' => true]);
        $this->model = VehicleModel::firstOrCreate(['brand_id' => $brand->id, 'name' => 'Fiesta'], ['is_active' => true]);
        $this->seller = User::factory()->create(['role' => 'seller']);
    }

    private function car(float $price, \DateTimeInterface $postedAt, string $status = 'active', array $extra = []): Listing
    {
        $v = Vehicle::create(['brand_id' => $this->model->brand_id, 'model_id' => $this->model->id, 'year' => 2006, 'mileage' => 142000]);
        $l = Listing::create($extra + [
            'user_id' => $this->seller->id, 'type' => 'vehicle', 'listable_type' => Vehicle::class, 'listable_id' => $v->id,
            'title' => 'Ford Fiesta '.$price, 'price' => $price, 'currency' => 'GBP', 'city' => 'Kyrenia', 'status' => $status, 'published_at' => $postedAt,
        ]);
        Listing::whereKey($l->id)->update(['created_at' => $postedAt]);

        return $l->fresh();
    }

    public function test_trend_is_monthly_median_and_needs_enough_prices_per_month(): void
    {
        // Three listings two months ago, three this month; a lone one last month is too thin to show.
        foreach ([1800, 2000, 2200] as $p) { $this->car($p, now()->subMonths(2)); }
        foreach ([2100, 2300, 2500] as $p) { $this->car($p, now()); }
        $this->car(5000, now()->subMonth());
        $current = $this->car(2400, now()->subHours(2));

        $trend = $this->getJson("/api/v1/listings/{$current->slug}/market-trend")->assertOk()->json();

        $this->assertSame('model', $trend['basis']);
        $this->assertCount(2, $trend['points']);
        $this->assertEquals(2000, $trend['points'][0]['median']);
        $this->assertSame(now()->subMonths(2)->format('Y-m'), $trend['points'][0]['month']);
        $this->assertSame(4, $trend['points'][1]['count']);
        $this->assertEquals(2350, $trend['points'][1]['median']); // 2100,2300,2400,2500
        $this->assertEquals(17.5, $trend['change_pct']);
    }

    public function test_no_trend_is_drawn_from_thin_data(): void
    {
        $only = $this->car(2000, now());
        $this->car(2100, now()->subMonths(3));

        $trend = $this->getJson("/api/v1/listings/{$only->slug}/market-trend")->assertOk()->json();

        $this->assertSame([], $trend['points']);
        $this->assertNull($trend['change_pct']);
    }

    public function test_scoring_saves_the_comparable_count_and_a_risk_level(): void
    {
        foreach ([1900, 2000, 2100, 2200] as $p) { $this->car($p, now()->subDays(5)); }
        $listing = $this->car(2050, now()->subDay());

        app(ScoringOrchestratorService::class)->scoreListing($listing);
        $listing->refresh();

        $this->assertSame(4, $listing->ai_comparable_count);
        $this->assertContains($listing->risk_level, ['low', 'medium']);
        $this->assertNotEmpty($listing->risk_note);
        $this->getJson("/api/v1/listings/{$listing->slug}")->assertOk()->assertJsonPath('listing.ai_comparable_count', 4);
    }

    public function test_risk_rises_for_suspiciously_cheap_or_reported_listings(): void
    {
        $risk = app(RiskAssessment::class);
        $cheap = $this->car(900, now());
        $price = ['has_estimate' => true, 'market_avg' => 2000];

        $this->assertSame('high', $risk->assess($cheap, $price, 45)['level']);
        $this->assertSame('low', $risk->assess($this->car(2000, now()), $price, 85)['level']);

        $normal = $this->car(2000, now());
        FraudReport::create(['listing_id' => $normal->id, 'reason' => 'scam', 'status' => 'pending']);
        $this->assertSame('high', $risk->assess($normal, $price, 85)['level']);

        $this->assertNull($risk->assess($this->car(2000, now()), ['has_estimate' => false], null)['level']);
    }

    public function test_area_insights_report_change_only_with_enough_listings(): void
    {
        foreach ([2000, 2000, 2000] as $p) { $this->car($p, now()->subDays(120)); }
        foreach ([2200, 2200, 2200] as $p) { $this->car($p, now()->subDays(10)); }
        $this->car(8000, now()->subDays(5), 'active', ['city' => 'Lapta']);

        $areas = collect($this->getJson('/api/v1/market/areas?type=vehicle')->assertOk()->json());

        $kyrenia = $areas->firstWhere('city', 'Kyrenia');
        $this->assertSame(6, $kyrenia['listings']);
        $this->assertEquals(10.0, $kyrenia['change_90d']);
        $this->assertNull($areas->firstWhere('city', 'Lapta')['change_90d']);
    }

    public function test_description_is_translated_faithfully_and_invalidated_on_edit(): void
    {
        config(['services.openai.key' => 'sk-test', 'services.ai_provider' => 'openai', 'services.openai.base_url' => 'https://ai.test/v1']);
        Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'en' => 'Economical city car, 2006 Ford Fiesta, petrol.', 'tr' => 'Ekonomik şehir aracı, 2006 Ford Fiesta, benzinli.', 'fa' => 'خودروی اقتصادی شهری، فورد فیستا ۲۰۰۶، بنزینی.',
        ])]]]])]);
        $listing = $this->car(2000, now(), 'active', ['description' => 'Ekonomik şehir aracı, 2006 Ford Fiesta, benzinli.']);

        (new TranslateListing($listing->id))->handle(app(\App\Services\AI\ListingTranslationService::class));

        $listing->refresh();
        $this->assertSame(['en', 'tr', 'fa'], array_keys($listing->description_i18n));
        $this->assertNotNull($listing->translated_at);
        $this->getJson("/api/v1/listings/{$listing->slug}")->assertJsonPath('listing.description_i18n.en', 'Economical city car, 2006 Ford Fiesta, petrol.');

        // Editing the text throws the old translations away.
        $seller = $this->seller; $seller->assignRole('seller');
        $this->actingAs($seller, 'sanctum')->putJson("/api/v1/listings/{$listing->id}", ['description' => 'A completely different and longer description of the car.'])->assertOk();
        $this->assertNull($listing->fresh()->description_i18n);
    }

    public function test_translation_does_nothing_without_an_ai_key_and_rejects_empty_answers(): void
    {
        config(['services.openai.key' => '']);
        Http::fake();
        $listing = $this->car(2000, now(), 'active', ['description' => 'A long enough description to translate here.']);
        $this->assertNull(app(\App\Services\AI\ListingTranslationService::class)->translate($listing));
        Http::assertNothingSent();

        config(['services.openai.key' => 'sk-test', 'services.ai_provider' => 'openai', 'services.openai.base_url' => 'https://ai.test/v1']);
        Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => '{"en":"","tr":"","fa":""}']]]])]);
        $this->assertNull(app(\App\Services\AI\ListingTranslationService::class)->translate($listing));
        $this->assertNull($listing->fresh()->description_i18n);
    }

    public function test_approval_queues_a_translation(): void
    {
        $listing = $this->car(2000, now(), 'pending');
        $this->actingAs(User::where('role', 'super_admin')->firstOrFail(), 'sanctum')->postJson("/api/v1/admin/listings/{$listing->id}/approve")->assertOk();
        Queue::assertPushed(TranslateListing::class, fn ($j) => $j->listingId === $listing->id);
    }
}
