<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_body_fuel_and_year_filters_narrow_results(): void
    {
        $u = User::factory()->create();
        $make = function (string $brand, string $body, string $fuel, int $year) use ($u) {
            $b = VehicleBrand::firstOrCreate(['name' => $brand], ['is_active' => true]);
            $m = VehicleModel::firstOrCreate(['brand_id' => $b->id, 'name' => $brand.' Model'], ['is_active' => true]);
            $v = Vehicle::create(['brand_id' => $b->id, 'model_id' => $m->id, 'year' => $year, 'body_type' => $body, 'fuel_type' => $fuel, 'condition' => 'used']);
            return Listing::create(['user_id' => $u->id, 'type' => 'vehicle', 'listable_type' => Vehicle::class, 'listable_id' => $v->id,
                'title' => "$brand $body", 'price' => 10000, 'currency' => 'GBP', 'status' => 'active', 'published_at' => now()]);
        };
        $toyotaSuv = $make('Toyota', 'SUV', 'hybrid', 2021);
        $make('BMW', 'Sedan', 'petrol', 2015);

        $this->getJson('/api/v1/listings?type=vehicle&brand=toyota')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $toyotaSuv->id);
        $this->getJson('/api/v1/listings?type=vehicle&body=SUV')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/listings?type=vehicle&fuel_type=Petrol')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/listings?type=vehicle&min_year=2020')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/listings?type=vehicle')->assertOk()->assertJsonCount(2, 'data');
    }
}
