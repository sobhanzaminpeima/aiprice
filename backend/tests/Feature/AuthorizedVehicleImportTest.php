<?php

namespace Tests\Feature;

use App\Models\CompetitorListing;
use App\Models\CompetitorSource;
use App\Models\Listing;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuthorizedVehicleImportService;
use App\Services\CompetitorCrawlerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthorizedVehicleImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_export_imports_gallery_and_is_idempotent(): void
    {
        Storage::fake('public');
        Queue::fake();
        $owner = User::factory()->create();
        $photo = UploadedFile::fake()->image('car.jpg');
        $rows = [[
            'external_id' => '123456', 'source_url' => 'https://www.kktcarabam.com/123456-test',
            'brand' => 'Test Brand', 'model' => 'Test Model', 'year' => 2020, 'price' => 12000,
            'currency' => 'GBP', 'city' => 'Girne', 'images' => [basename($photo->getPathname())],
            'engine_size' => '1400', 'color' => 'white', 'body_type' => 'sedan',
            'description' => 'Seller reports a recent inspection.', 'images_ai_edited' => true,
        ]];
        $service = app(AuthorizedVehicleImportService::class);
        $service->import($rows, dirname($photo->getPathname()), $owner, true);
        $this->assertSame(0, Listing::count());
        $this->assertSame(1, $service->import($rows, dirname($photo->getPathname()), $owner)['created']);
        $this->assertSame(1, $service->import($rows, dirname($photo->getPathname()), $owner)['skipped']);
        $this->assertSame(1, Vehicle::count());
        $listing = Listing::firstOrFail();
        Storage::disk('public')->assertExists(substr($listing->thumbnail, strlen('/storage/')));
        $this->assertSame($rows[0]['source_url'], $listing->meta['source_url']);
        $this->assertSame('active', $listing->status);
        $this->assertSame('1400', $listing->listable->engine_size);
        $this->assertSame('white', $listing->listable->color);
        $this->assertSame('sedan', $listing->listable->body_type);
        $this->assertTrue($listing->meta['images_ai_edited']);
        $this->assertStringContainsString('Seller reports a recent inspection.', $listing->description);
        $this->assertStringContainsString('AI-edited', $listing->description);
        $this->assertSame('123456', $listing->meta['external_id']);
        // Provenance stays server-side: buyers never see the source site.
        $body = $this->getJson('/api/v1/listings/'.$listing->slug)->assertOk()->assertJsonMissingPath('listing.meta')->getContent();
        $this->assertStringNotContainsStringIgnoringCase('kktcarabam', $body);
    }

    public function test_missing_images_do_not_create_partial_inventory(): void
    {
        $owner = User::factory()->create();
        try {
            app(AuthorizedVehicleImportService::class)->import([[
                'external_id' => '123456', 'source_url' => 'https://www.kktcarabam.com/123456-test',
                'brand' => 'Test', 'model' => 'Test', 'year' => 2020, 'price' => 12000,
                'currency' => 'GBP', 'city' => 'Girne', 'images' => ['missing-photo.jpg'],
            ]], storage_path(), $owner);
            $this->fail('Invalid export was accepted.');
        } catch (ValidationException) {
            $this->assertSame(0, Listing::count());
            $this->assertSame(0, Vehicle::count());
        }
    }

    public function test_cloudflare_block_never_generates_sample_market_data(): void
    {
        Http::fake(['*' => Http::response('Sorry, you have been blocked', 403)]);
        $source = CompetitorSource::create(['name' => 'KKT Carabam', 'url' => 'https://www.kktcarabam.com', 'type' => 'vehicle', 'is_active' => true]);
        $result = app(CompetitorCrawlerService::class)->crawlSource($source);
        $this->assertSame(1, $result['errors']);
        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, CompetitorListing::count());
        $this->assertNull($source->fresh()->last_scraped_at);
    }
}
