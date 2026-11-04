<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Services\AI\PriceIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private function car(User $u, VehicleModel $m, int $year, float $price, string $currency, string $city = 'Girne'): Listing
    {
        $v = Vehicle::create(['brand_id' => $m->brand_id, 'model_id' => $m->id, 'year' => $year, 'condition' => 'used']);

        return Listing::create([
            'user_id' => $u->id, 'type' => 'vehicle', 'listable_type' => Vehicle::class, 'listable_id' => $v->id,
            'title' => "{$year} car", 'price' => $price, 'currency' => $currency, 'city' => $city,
            'status' => 'active', 'published_at' => now(),
        ]);
    }

    public function test_no_verdict_without_enough_comparables(): void
    {
        $u = User::factory()->create();
        $brand = VehicleBrand::create(['name' => 'Toyota', 'is_active' => true]);
        $model = VehicleModel::create(['brand_id' => $brand->id, 'name' => 'Corolla', 'is_active' => true]);
        $target = $this->car($u, $model, 2020, 15000, 'GBP');

        $r = app(PriceIntelligenceService::class)->analyze($target);
        $this->assertFalse($r['has_estimate']);
        $this->assertSame('insufficient_data', $r['price_status']);
    }

    public function test_compares_same_currency_and_model_using_the_median(): void
    {
        $u = User::factory()->create();
        $brand = VehicleBrand::create(['name' => 'Toyota', 'is_active' => true]);
        $model = VehicleModel::create(['brand_id' => $brand->id, 'name' => 'Corolla', 'is_active' => true]);
        foreach ([14000, 15000, 16000] as $p) {
            $this->car($u, $model, 2020, $p, 'GBP');
        }
        // Same car in Turkish lira must never be averaged in.
        foreach ([600000, 650000, 700000] as $p) {
            $this->car($u, $model, 2020, $p, 'TRY');
        }
        $target = $this->car($u, $model, 2021, 18000, 'GBP');

        $r = app(PriceIntelligenceService::class)->analyze($target);
        $this->assertTrue($r['has_estimate']);
        $this->assertSame(3, $r['comparable_count']);
        $this->assertEquals(15000, $r['market_avg']);
        $this->assertSame('slightly_overpriced', $r['price_status']); // +20% over median
        $this->assertStringContainsString('not a valuation or a guarantee', end($r['reasons']));
    }
}
