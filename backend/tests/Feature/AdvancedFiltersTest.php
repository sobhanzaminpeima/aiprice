<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function home(array $listing, array $property = []): Listing
    {
        $p = Property::create($property + ['title' => 'Villa', 'property_type_id' => PropertyType::firstOrCreate(['name' => 'Villa'], ['is_active' => true])->id, 'bedrooms' => 3, 'area_sqm' => 150]);

        return Listing::create($listing + ['user_id' => User::factory()->create()->id, 'type' => 'property', 'listable_type' => Property::class, 'listable_id' => $p->id,
            'title' => 'Villa', 'price' => 200000, 'currency' => 'GBP', 'status' => 'active', 'published_at' => now()]);
    }

    public function test_ai_verdict_freshness_photos_size_features_and_battery_sort(): void
    {
        $this->seed(DatabaseSeeder::class);
        $good = $this->home(['title' => 'Good', 'market_min_price' => 190000, 'market_max_price' => 230000, 'ai_price_status' => 'fair', 'price_battery_percent' => 95, 'thumbnail' => '/x.jpg'], ['has_pool' => true, 'area_sqm' => 220]);
        $fair = $this->home(['title' => 'Fair', 'market_min_price' => 190000, 'market_max_price' => 230000, 'ai_price_status' => 'fair', 'price_battery_percent' => 75]);
        $cheap = $this->home(['title' => 'Cheap', 'price' => 150000, 'market_min_price' => 190000, 'market_max_price' => 230000, 'ai_price_status' => 'fair', 'price_battery_percent' => 99, 'published_at' => now()->subDays(20)]);
        $this->home(['title' => 'Unchecked', 'ai_price_status' => 'insufficient_data']);

        $titles = fn (string $q) => collect($this->getJson("/api/v1/listings?type=property&{$q}")->assertOk()->json('data'))->pluck('title')->sort()->values()->all();

        $this->assertSame(['Cheap', 'Fair', 'Good'], $titles('ai=checked'));
        $this->assertSame(['Cheap', 'Good'], $titles('ai=good'));
        $this->assertSame(['Cheap'], $titles('ai=below'));
        $this->assertSame(['Fair', 'Good', 'Unchecked'], $titles('posted_within=7'));
        $this->assertSame(['Good'], $titles('with_photos=1'));
        $this->assertSame(['Good'], $titles('has_pool=1'));
        $this->assertSame(['Good'], $titles('min_area=200'));

        $order = collect($this->getJson('/api/v1/listings?type=property&sort=battery')->json('data'))->pluck('title')->all();
        $this->assertSame(['Cheap', 'Good', 'Fair', 'Unchecked'], $order);

        $this->getJson('/api/v1/listings?sort=nonsense')->assertStatus(422);
    }
}
