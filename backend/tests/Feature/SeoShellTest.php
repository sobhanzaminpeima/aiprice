<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoShellTest extends TestCase
{
    use RefreshDatabase;

    private string $spa;
    private bool $created = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['app.frontend_url' => 'https://aiprice.app']);
        $this->spa = public_path('spa.html');
        if (! is_file($this->spa)) {
            file_put_contents($this->spa, '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="description" content="generic" /><title>Generic</title></head><body><div id="app"></div><script type="module" src="/assets/app.js"></script></body></html>');
            $this->created = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->created) {
            @unlink($this->spa);
        }
        parent::tearDown();
    }

    private function car(): Listing
    {
        $b = VehicleBrand::firstOrCreate(['name' => 'Ford'], ['is_active' => true]);
        $m = VehicleModel::firstOrCreate(['brand_id' => $b->id, 'name' => 'Fiesta'], ['is_active' => true]);
        $v = Vehicle::create(['brand_id' => $b->id, 'model_id' => $m->id, 'year' => 2006, 'mileage' => 142000, 'fuel_type' => 'petrol']);

        return Listing::create(['user_id' => User::factory()->create()->id, 'type' => 'vehicle', 'listable_type' => Vehicle::class, 'listable_id' => $v->id,
            'title' => '2006 Ford Fiesta <b>', 'price' => 2000, 'currency' => 'GBP', 'city' => 'Kyrenia', 'status' => 'active', 'published_at' => now(),
            'description' => 'Economical city car.', 'market_min_price' => 1900, 'market_max_price' => 2100, 'ai_price_status' => 'fair', 'price_battery_percent' => 96]);
    }

    public function test_listing_page_gets_its_own_title_description_schema_and_content(): void
    {
        $l = $this->car();
        $html = $this->get("/listings/{$l->slug}")->assertOk()->getContent();

        $this->assertStringContainsString('<title>2006 Ford Fiesta &lt;b&gt; for sale in Kyrenia – £2,000</title>', $html);
        $this->assertStringNotContainsString('content="generic"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://aiprice.app/listings/'.$l->slug.'">', $html);
        $this->assertStringContainsString('"@type":"Car"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString('"price":2000', $html);
        $this->assertStringContainsString('AI fair price range: £1,900–£2,100, price battery 96%.', $html);
        $this->assertStringContainsString('<h1>2006 Ford Fiesta &lt;b&gt;</h1>', $html);
        $this->assertStringNotContainsString('<b></h1>', $html); // escaped, never raw
    }

    public function test_category_pages_list_listings_and_home_has_site_schema(): void
    {
        $l = $this->car();
        $cars = $this->get('/cars')->assertOk()->getContent();
        $this->assertStringContainsString('Cars for Sale in North Cyprus', $cars);
        $this->assertStringContainsString('href="/listings/'.$l->slug.'"', $cars);
        $this->assertStringContainsString('href="/cars?city=Girne"', $cars);

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('"@type":"WebSite"', $home);
        $this->assertStringContainsString('"@type":"SearchAction"', $home);
        $this->assertStringContainsString('"@type":"FAQPage"', $home);
        $this->assertStringContainsString('"@type":"FAQPage"', $cars);
    }

    public function test_missing_listings_and_unknown_pages_return_404_and_noindex(): void
    {
        $this->get('/listings/does-not-exist')->assertNotFound()->assertSee('noindex, nofollow', false);
        $this->get('/some/unknown/page')->assertNotFound()->assertSee('noindex, nofollow', false);
    }

    public function test_private_pages_are_noindex(): void
    {
        $this->get('/dashboard/listings')->assertOk()->assertSee('noindex, nofollow', false);
    }
}
