<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPriceCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::flush();
        config(['services.ai_provider' => 'openai', 'services.openai.base_url' => 'https://ai.test/v1', 'services.openai.key' => 'sk-test']);
        $this->actingAs(User::where('role', 'super_admin')->firstOrFail(), 'sanctum');
    }

    private function listing(float $price = 2000): Listing
    {
        $b = VehicleBrand::firstOrCreate(['name' => 'Ford'], ['is_active' => true]);
        $m = VehicleModel::firstOrCreate(['brand_id' => $b->id, 'name' => 'Fiesta'], ['is_active' => true]);
        $v = Vehicle::create(['brand_id' => $b->id, 'model_id' => $m->id, 'year' => 2006, 'mileage' => 142000]);

        return Listing::create(['user_id' => User::factory()->create()->id, 'type' => 'vehicle', 'listable_type' => Vehicle::class, 'listable_id' => $v->id,
            'title' => 'Fiesta', 'price' => $price, 'currency' => 'GBP', 'city' => 'Kyrenia', 'status' => 'active', 'published_at' => now()]);
    }

    private function ai(array $range): void
    {
        Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => 'Sure: '.json_encode($range).' hope that helps']]]])]);
    }

    public function test_run_now_fills_the_price_battery_from_an_ai_estimate(): void
    {
        $l = $this->listing();
        $this->ai(['low' => 1700, 'typical' => 2000, 'high' => 2300, 'note' => 'ok']);

        $r = $this->postJson('/api/v1/admin/ai/price-check-run')->assertOk()->json();

        $this->assertSame(1, $r['processed']);
        $this->assertSame(1, $r['with_estimate']);
        $this->assertNull($r['reason']);
        $l->refresh();
        $this->assertNotNull($l->price_battery_percent);
        $this->assertEquals(1700, $l->market_min_price);
    }

    public function test_run_now_says_why_when_there_is_no_ai_key(): void
    {
        $this->listing();
        config(['services.openai.key' => '']);

        $r = $this->postJson('/api/v1/admin/ai/price-check-run')->assertOk()->json();

        $this->assertSame(1, $r['still_without']);
        $this->assertStringContainsString('No AI key is saved', $r['reason']);
    }

    public function test_diagnose_reports_provider_errors_in_plain_words(): void
    {
        Http::fake(['ai.test/*' => Http::sequence()
            ->push(['error' => ['message' => 'bad key']], 401)
            ->push(['choices' => [['message' => ['content' => '{"low":0,"typical":0,"high":0,"note":"unknown"}']]]])
            ->push(['choices' => [['message' => ['content' => 'Here: {"low":1700,"typical":2000,"high":2300,"note":""}']]]])]);

        $r = $this->postJson('/api/v1/admin/ai/diagnose')->assertOk()->json();
        $this->assertFalse($r['ok']);
        $this->assertTrue($r['key_saved']);
        $this->assertStringContainsString('rejected the key (HTTP 401)', $r['reason']);

        $this->assertStringContainsString('cannot judge', $this->postJson('/api/v1/admin/ai/diagnose')->json('reason'));
        $this->assertTrue($this->postJson('/api/v1/admin/ai/diagnose')->json('ok'));
    }

    public function test_provider_without_response_format_support_gets_a_second_plain_request(): void
    {
        Http::fake(['ai.test/*' => Http::sequence()
            ->push(['error' => ['message' => 'response_format unsupported']], 400)
            ->push(['choices' => [['message' => ['content' => '{"low":1700,"typical":2000,"high":2300,"note":""}']]]])]);

        $this->assertTrue($this->postJson('/api/v1/admin/ai/diagnose')->json('ok'));
        Http::assertSentCount(2);
    }

    public function test_only_staff_with_ai_permission_can_use_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'seller']), 'sanctum');
        $this->postJson('/api/v1/admin/ai/price-check-run')->assertForbidden();
        $this->postJson('/api/v1/admin/ai/diagnose')->assertForbidden();
    }
}
