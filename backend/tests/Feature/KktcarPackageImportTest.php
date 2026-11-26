<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeListingPrice;
use App\Models\Imports\SourceListing;
use App\Models\Listing;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Services\AI\AiMarketEstimateService;
use App\Services\AI\PriceIntelligenceService;
use App\Services\Imports\KktcarPackageImporter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class KktcarPackageImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $this->dir = sys_get_temp_dir().'/kktcar-pkg-'.Str::random(8);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function record(int $n, array $o = []): array
    {
        $slug = "2019-toyota-corolla-nicosia-t{$n}";

        return array_replace_recursive([
            'external_id' => sprintf('KKT_%04d', $n), 'source_listing_id' => (string) Str::uuid(), 'source_slug' => $slug,
            'source_url' => "https://kktcar.com/en/listing/{$slug}", 'title' => "2019 Toyota Corolla — Automatic — Petrol #{$n}",
            'slug' => "2019-toyota-corolla-automatic-petrol-nicosia-t{$n}", 'brand' => 'Toyota', 'model' => 'Corolla', 'variant' => null,
            'year' => 2019, 'price' => 10000 + $n, 'currency' => 'GBP', 'price_negotiable' => null, 'previous_price' => null, 'price_change' => null,
            'open_to_trade' => false, 'mileage' => 40000 + $n, 'mileage_unit' => 'km', 'mileage_source_value' => 40000 + $n,
            'fuel_type' => 'Petrol', 'transmission' => 'Automatic', 'engine_capacity' => '1.6 L', 'body_type' => 'Sedan', 'color' => 'white',
            'steering' => null, 'location' => 'Nicosia', 'country' => 'Northern Cyprus',
            'description' => 'Original AIPrice text built from facts. Not verified by AIPrice.', 'short_description' => 'Short.',
            'features' => [], 'seller' => ['name' => null, 'type' => 'Private seller', 'phone' => null, 'whatsapp' => null, 'contact_publication_authorized' => false],
            'images' => [], 'gallery' => ['count_on_source' => 8, 'source_cdn_folder' => 'https://cdn.kktcar.com/x/', 'files_downloaded' => 0],
            'seo' => ['title' => "2019 Toyota Corolla for sale in Nicosia | AIPrice", 'description' => 'SEO description.'],
            'quality_score' => 80, 'source_listed_date' => '2026-11-01', 'source_checked_at' => '2026-11-16T00:00:00+02:00',
            'source_status' => 'loaded', 'data_flags' => [], 'record_complete' => true, 'status' => 'draft', 'rights_clearance_required' => true,
        ], $o);
    }

    private function writePackage(array $records, ?int $manifestCount = null, bool $badChecksum = false): void
    {
        $json = json_encode(['schema_version' => '1.0', 'source' => 'kktcar.com', 'destination' => 'aiprice.app',
            'target_count' => count($records), 'vehicles' => $records], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents("{$this->dir}/vehicles.json", $json);
        file_put_contents("{$this->dir}/manifest.json", json_encode(['package' => 'AIPRICE_KKTCAR_IMPORT', 'record_count' => $manifestCount ?? count($records)]));
        file_put_contents("{$this->dir}/checksums.json", json_encode(['vehicles.json' => $badChecksum ? str_repeat('0', 64) : hash('sha256', $json)]));
    }

    public function test_package_imports_drafts_only_and_keeps_values_exactly(): void
    {
        $records = [
            $this->record(1),
            $this->record(2, ['currency' => 'TRY', 'price' => 950000, 'location' => 'İskele']),
            $this->record(3, ['mileage' => null, 'mileage_source_value' => 0, 'record_complete' => false,
                'data_flags' => ['mileage_implausible: source shows 0 km for a 2012 vehicle; needs manual check']]),
            $this->record(4, ['engine_capacity' => null, 'seller' => ['name' => 'Should Not Be Stored', 'phone' => '+90 533 000 0000']]),
            $this->record(5, ['currency' => 'XYZ']),                                               // invalid
            $this->record(6, ['brand' => 'Toyota', 'model' => 'Corolla', 'price' => 10001, 'mileage' => 40001]), // same facts as #1
        ];
        // A listing already using #1's slug forces a collision suffix.
        $brand = VehicleBrand::create(['name' => 'Other', 'is_active' => true]);
        $model = VehicleModel::create(['brand_id' => $brand->id, 'name' => 'X', 'is_active' => true]);
        $v = Vehicle::create(['brand_id' => $brand->id, 'model_id' => $model->id, 'year' => 2010]);
        Listing::create(['user_id' => User::factory()->create()->id, 'type' => 'vehicle', 'listable_type' => Vehicle::class, 'listable_id' => $v->id,
            'title' => 'Existing', 'slug' => $records[0]['slug'], 'price' => 1, 'currency' => 'GBP', 'status' => 'active']);
        $this->writePackage($records);

        $res = app(KktcarPackageImporter::class)->import($this->dir);

        $this->assertCount(4, $res['created']);
        $this->assertSame(['KKT_0005'], array_column($res['rejected'], 'external_id'));
        $this->assertSame(['KKT_0006'], array_column($res['skipped'], 'external_id'));
        $this->assertSame(['KKT_0003'], array_column($res['flagged'], 'external_id'));
        $this->assertSame(['checked' => 4, 'mismatches' => []], $res['reconciliation']);

        $imported = Listing::whereNotNull('import_source_key')->get();
        $this->assertCount(4, $imported);
        $this->assertTrue($imported->every(fn ($l) => $l->status === 'draft' && $l->images === [] && $l->thumbnail === null));
        $this->assertSame($records[0]['slug'].'-2', Listing::where('import_source_key', 'kktcar:'.$records[0]['source_listing_id'])->value('slug'));

        $try = Listing::with('listable')->where('import_source_key', 'kktcar:'.$records[1]['source_listing_id'])->first();
        $this->assertSame(['950000.00', 'TRY', 'İskele'], [$try->price, $try->currency, $try->city]);
        $this->assertSame('1.6 L', $try->listable->engine_size);
        $this->assertSame(['petrol', 'automatic', 'sedan'], [$try->listable->fuel_type, $try->listable->transmission, $try->listable->body_type]);

        $flagged = SourceListing::where('source_listing_id', $records[2]['source_listing_id'])->first();
        $this->assertSame('needs_review', $flagged->status);
        $this->assertNull($flagged->listing->listable->mileage);
        $this->assertSame('clearance_required', $flagged->rights_status);
        $this->assertNull(Listing::with('listable')->where('import_source_key', 'kktcar:'.$records[3]['source_listing_id'])->first()->listable->engine_size);

        // No contacts anywhere.
        $this->assertSame(0, SourceListing::whereNotNull('seller_display_name')->orWhereNotNull('seller_phone_e164')->count());
        $this->assertStringNotContainsString('Should Not Be Stored', json_encode(SourceListing::pluck('data')));
        $this->assertNotEmpty(array_filter($res['warnings'], fn ($w) => str_contains($w, 'KKT_0004')));
        Queue::assertNotPushed(AnalyzeListingPrice::class);

        // Publication is blocked by rights and review even if source permission were granted.
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/imports/listings/{$flagged->id}/approve")->assertStatus(422)
            ->assertJsonFragment(['blockers' => ['listing_data_permission_pending_authorization', 'image_permission_pending_authorization',
                'rights_clearance_required', 'needs_manual_review', 'no_stored_images']]);
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/imports/listings/{$flagged->id}/resolve-review", ['note' => 'Checked with seller'])->assertOk();
        $this->assertSame('imported', $flagged->fresh()->status);

        // Re-running is idempotent.
        $again = app(KktcarPackageImporter::class)->import($this->dir);
        $this->assertCount(0, $again['created']);
        $this->assertCount(5, $again['skipped']);
        $this->assertSame(4, Listing::whereNotNull('import_source_key')->count());
    }

    public function test_integrity_failures_stop_before_any_write(): void
    {
        $this->writePackage([$this->record(1)], badChecksum: true);
        try {
            app(KktcarPackageImporter::class)->import($this->dir);
            $this->fail('Expected checksum failure');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('checksums', $e->getMessage());
        }
        $this->assertSame(0, Listing::count());

        $this->writePackage([$this->record(1)], manifestCount: 35);
        $res = app(KktcarPackageImporter::class)->import($this->dir, dryRun: true);
        $this->assertNotEmpty($res['warnings']);
        $this->assertSame(0, Listing::count(), 'dry run writes no listings');
    }

    public function test_imported_listing_gets_no_ai_fallback_estimate_and_exposes_seo_and_attribution(): void
    {
        $this->writePackage([$this->record(1)]);
        app(KktcarPackageImporter::class)->import($this->dir);
        $listing = Listing::whereNotNull('import_source_key')->firstOrFail();

        // With AI estimates for imports switched off, no AI range is made up.
        config(['services.ai_price.estimate_imported' => false]);
        $this->mock(AiMarketEstimateService::class)->shouldNotReceive('estimate');
        $price = app(PriceIntelligenceService::class)->analyze($listing);
        $this->assertFalse($price['has_estimate']);
        $this->assertSame('insufficient_data', $price['price_status']);

        // Render check of the public payload (activated only inside this test DB).
        $listing->update(['status' => 'active']);
        $this->getJson("/api/v1/listings/{$listing->slug}")->assertOk()
            ->assertJsonPath('seo.meta_title', '2019 Toyota Corolla for sale in Nicosia | AIPrice')
            ->assertJsonPath('source_attribution.source_name', 'kktcar.com')
            ->assertJsonPath('source_attribution.original_url', 'https://kktcar.com/en/listing/2019-toyota-corolla-nicosia-t1')
            ->assertJsonPath('seller_contact.has_phone', false)
            ->assertJsonPath('listing.images', [])
            ->assertJsonPath('listing.market_avg_price', null)
            ->assertJsonMissingPath('listing.meta');
    }

    public function test_imported_listing_gets_a_labelled_ai_estimate_by_default(): void
    {
        $this->writePackage([$this->record(1)]);
        app(KktcarPackageImporter::class)->import($this->dir);
        $listing = Listing::whereNotNull('import_source_key')->firstOrFail();

        $this->mock(AiMarketEstimateService::class)->shouldReceive('estimate')->once()
            ->andReturn(['typical' => 15000.0, 'low' => 13500.0, 'high' => 16500.0, 'note' => '']);
        $price = app(PriceIntelligenceService::class)->analyze($listing);

        $this->assertTrue($price['has_estimate']);
        $this->assertSame('ai_model', $price['source']);
        $this->assertSame(30, $price['confidence']);
    }
}
