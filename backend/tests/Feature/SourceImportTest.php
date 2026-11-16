<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeListingPrice;
use App\Jobs\RecheckSourceListing;
use App\Jobs\RunSourceImport;
use App\Models\AuditLog;
use App\Models\Imports\ImportRun;
use App\Models\Imports\ImportSource;
use App\Models\Imports\SourceListing;
use App\Models\Imports\SourceListingImage;
use App\Models\Listing;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\Kktcarabam\KktcarabamAdapter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FixtureAdapter;
use Tests\Support\KktFixture;
use Tests\TestCase;

class SourceImportTest extends TestCase
{
    use RefreshDatabase;

    private ImportSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        Queue::fake();
        ImportSource::ensureKnown();
        $this->source = ImportSource::where('key', 'kktcarabam')->firstOrFail();
    }

    private function runImport(string $mode, FixtureAdapter $adapter, int $target = 20, array $opts = []): ImportRun
    {
        $run = ImportRun::create(['import_source_id' => $this->source->id, 'mode' => $mode, 'status' => 'queued', 'target' => $target]);

        return app(ImportPipeline::class)->run($run, $adapter, $opts + ['pages' => 1, 'max_fetch' => 200]);
    }

    private function user(string $role): User
    {
        $u = User::factory()->create(['role' => $role, 'is_active' => true]);
        $u->assignRole($role);

        return $u;
    }

    private function authorize(array $perms = ['listing_data_permission', 'image_permission']): void
    {
        $this->source->update(array_fill_keys($perms, ImportSource::AUTHORIZED) + ['permission_reference' => 'Test agreement #1']);
    }

    public function test_dry_run_selects_without_writing_listings_or_media(): void
    {
        $pool = KktFixture::pool(26);
        $pool[] = ['id' => '920001', 'price' => null, 'make' => 'Opel', 'model' => 'Astra'];           // missing price
        $pool[] = ['id' => '920002', 'title_year' => 2009, 'make' => 'Seat', 'model' => 'Ibiza'];       // conflicting year
        $pool[] = ['id' => '920003'] + $pool[0];                                                        // exact duplicate vehicle
        $pool[count($pool) - 1]['id'] = '920003';

        $run = $this->runImport('dry_run', KktFixture::adapter($pool));

        $this->assertSame('completed', $run->status);
        $this->assertSame(29, $run->candidates_discovered);
        $this->assertSame(26, $run->eligible);
        $this->assertSame(20, $run->selected);
        $this->assertSame(2, $run->rejected);
        $this->assertSame(1, $run->skipped_duplicates);
        $this->assertSame(0, Listing::count());
        $this->assertSame(0, Vehicle::count());
        $this->assertSame(0, SourceListingImage::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(['missing_price'], SourceListing::where('source_listing_id', '920001')->value('rejection_reasons'));
        $this->assertContains('conflict:year_title_vs_specs', SourceListing::where('source_listing_id', '920002')->first()->rejection_reasons);
        $this->assertStringStartsWith('duplicate_of:', SourceListing::where('source_listing_id', '920003')->first()->rejection_reasons[0]);
        Queue::assertNotPushed(AnalyzeListingPrice::class);
    }

    public function test_import_creates_private_drafts_with_variants_and_is_idempotent(): void
    {
        $pool = KktFixture::pool(20);
        $run = $this->runImport('import', KktFixture::adapter($pool));

        $this->assertSame('completed', $run->status, (string) $run->error);
        $this->assertSame(20, $run->imported);
        $this->assertSame(20, Listing::where('status', 'draft')->count());
        $this->assertSame(0, Listing::where('status', 'active')->count());
        $listing = Listing::firstOrFail();
        $this->assertSame([], $listing->images, 'unauthorized images are never attached publicly');
        $this->assertSame('used', $listing->listable->condition);
        $this->assertStringContainsString('not been independently verified', $listing->description);
        $this->assertStringNotContainsString('Test açıklama', $listing->description, 'seller text is not copied verbatim');

        $sl = SourceListing::where('listing_id', $listing->id)->firstOrFail();
        $this->assertSame('imported', $sl->status);
        $this->assertSame('pending_authorization', $sl->image_publication_status);
        $this->assertSame(1, $sl->images()->where('status', 'duplicate')->count(), 'exact duplicate removed');
        $stored = $sl->images()->where('status', 'stored')->get();
        $this->assertTrue($stored->first()->is_cover);
        $this->assertSame([0, 2], [$stored[0]->position, $stored[1]->position], 'gallery order preserved');
        foreach ($stored as $img) {
            Storage::disk('local')->assertExists([$img->original_path, $img->webp_path, $img->thumb_path]);
            [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($img->thumb_path));
            $this->assertSame([480, 360], [$w, $h], 'aspect ratio preserved');
            $this->assertSame('https://www.kktcarabam.com/uploads/images/2026/11/10/10/img-'.$sl->source_listing_id.'-'.($img->position + 1).'-900_675.jpg', $img->source_url);
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame($run->images_stored, SourceListingImage::where('status', 'stored')->count());

        $again = $this->runImport('import', KktFixture::adapter($pool));
        $this->assertSame(0, $again->imported);
        $this->assertSame(20, $again->skipped_duplicates);
        $this->assertSame(20, Listing::count());
        $this->assertSame(20, Vehicle::count());
    }

    public function test_partial_failures_are_isolated(): void
    {
        $pool = KktFixture::pool(20);
        $run = $this->runImport('import', KktFixture::adapter($pool, failImagesFor: ['910003']));

        $this->assertSame('completed', $run->status);
        $this->assertSame(19, $run->imported);
        $this->assertSame(1, $run->errors_count);
        $failed = SourceListing::where('source_listing_id', '910003')->firstOrFail();
        $this->assertSame('failed', $failed->status);
        $this->assertNull($failed->listing_id);
        $this->assertStringContainsString('No images could be stored', $failed->last_error);
    }

    public function test_blocked_source_stops_the_run_without_writes(): void
    {
        $run = $this->runImport('import', KktFixture::adapter(KktFixture::pool(20), blocked: true));
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('403', $run->error);
        $this->assertSame(0, Listing::count());
    }

    public function test_unauthorized_contacts_and_individual_names_are_never_stored_or_shown(): void
    {
        $pool = array_map(fn ($l) => $l + ['phone' => '0533 812 34 56'], KktFixture::pool(20));
        $this->runImport('import', KktFixture::adapter($pool));
        $this->authorize();

        $sl = SourceListing::with('source')->whereNotNull('listing_id')->firstOrFail();
        $this->assertNull($sl->seller_phone);
        $this->assertNull($sl->seller_phone_e164);
        $this->assertNull($sl->seller_display_name, 'private individual name needs contact permission');
        $this->assertSame('pending_authorization', $sl->contact_publication_status);

        $admin = $this->user('admin');
        $admin->update(['phone' => '+90 533 000 1111']);
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/imports/listings/{$sl->id}/approve")->assertOk();
        $listing = $sl->listing->fresh();
        // The importing system account and admins never leak their own phone.
        $res = $this->getJson("/api/v1/listings/{$listing->slug}")->assertOk();
        $res->assertJsonPath('seller_contact.has_phone', false)
            ->assertJsonPath('seller_contact.can_message', false)
            ->assertJsonPath('seller_contact.display_name', 'Seller (imported listing)')
            ->assertJsonMissingPath('listing.meta');
        $this->assertStringNotContainsString('0533', $res->getContent());
        $this->assertStringNotContainsString('Test Seller', $res->getContent());
        $this->actingAs($this->user('buyer'), 'sanctum')->getJson("/api/v1/listings/{$listing->id}/seller-phone")->assertNotFound();

        // Cannot mark a contact authorized while the source has no contact permission.
        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/imports/listings/{$sl->id}/contact", [
            'seller_phone' => '+90 533 812 3456', 'contact_publication_status' => 'authorized',
        ])->assertStatus(422);
    }

    public function test_publication_requires_recorded_permission_and_renders_attribution(): void
    {
        $this->runImport('import', KktFixture::adapter(KktFixture::pool(20)));
        $admin = $this->user('admin');
        $sl = SourceListing::whereNotNull('listing_id')->firstOrFail();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/imports/listings/{$sl->id}/approve")
            ->assertStatus(422)->assertJsonFragment(['blockers' => ['listing_data_permission_pending_authorization', 'image_permission_pending_authorization']]);
        $this->getJson('/api/v1/listings/'.$sl->listing->slug)->assertNotFound();

        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/imports/sources/{$this->source->id}", [
            'listing_data_permission' => 'authorized', 'image_permission' => 'authorized',
        ])->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/imports/sources/{$this->source->id}", [
            'listing_data_permission' => 'authorized', 'image_permission' => 'authorized',
            'permission_reference' => 'Signed agreement 2026-11-01', 'permission_granted_at' => '2026-11-01',
        ])->assertOk();
        $this->assertTrue(AuditLog::where('action', 'import.source_updated')->exists());

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/imports/listings/{$sl->id}/approve")->assertOk();
        Queue::assertPushed(AnalyzeListingPrice::class);
        $listing = $sl->listing->fresh();
        $this->assertSame('active', $listing->status);
        $this->assertNotEmpty($listing->images);
        Storage::disk('public')->assertExists(substr($listing->thumbnail, strlen('/storage/')));

        $this->getJson("/api/v1/listings/{$listing->slug}")->assertOk()
            ->assertJsonPath('source_attribution.imported', true)
            ->assertJsonPath('source_attribution.source_name', 'kktcarabam.com')
            ->assertJsonPath('source_attribution.original_url', $sl->canonical_url)
            ->assertJsonPath('source_attribution.seller_claims_verified', false)
            ->assertJsonPath('listing.price', number_format((float) $sl->data['asking_price'], 2, '.', ''))
            ->assertJsonPath('listing.market_avg_price', null);

        // Revoking the image permission withdraws the public listing and its media.
        $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/imports/sources/{$this->source->id}", ['image_permission' => 'denied'])
            ->assertOk()->assertJsonPath('withdrawn', 1);
        $this->assertSame('draft', $listing->fresh()->status);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_source_removal_recheck_and_removal_requests(): void
    {
        $adapter = KktFixture::adapter(KktFixture::pool(20));
        $this->runImport('import', $adapter);
        $this->authorize();
        $admin = $this->user('admin');
        [$a, $b] = SourceListing::with('source')->whereNotNull('listing_id')->take(2)->get()->all();
        foreach ([$a, $b] as $sl) {
            $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/imports/listings/{$sl->id}/approve")->assertOk();
        }

        // Listing disappears at the source → recheck unpublishes it.
        $adapter->forget($a->source_listing_id);
        $this->assertSame(['status' => 'gone'], app(ImportPipeline::class)->recheck($a->fresh('source'), $adapter));
        $this->assertSame('draft', $a->listing->fresh()->status);
        $this->assertFalse($a->fresh()->source_active);

        // Removal request → archived, public media deleted, contact cleared, audited.
        $thumb = substr($b->listing->fresh()->thumbnail, strlen('/storage/'));
        Storage::disk('public')->assertExists($thumb);
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/imports/listings/{$b->id}/remove", ['reason' => 'Seller asked for removal'])->assertOk();
        Storage::disk('public')->assertMissing($thumb);
        $this->assertSame('removed', $b->fresh()->status);
        $this->assertSoftDeleted('listings', ['id' => $b->listing_id]);
        $this->assertTrue(AuditLog::where('action', 'import.listing_removed')->where('model_id', $b->listing_id)->exists());

        // A removed listing is not re-imported on the next run.
        $next = $this->runImport('import', KktFixture::adapter(KktFixture::pool(20)));
        $this->assertSame(0, $next->imported);
    }

    public function test_admin_api_authorization_and_signed_previews(): void
    {
        $this->getJson('/api/v1/admin/imports')->assertUnauthorized();
        $this->actingAs($this->user('buyer'), 'sanctum')->getJson('/api/v1/admin/imports')->assertForbidden();
        $this->actingAs($this->user('moderator'), 'sanctum')->getJson('/api/v1/admin/imports')->assertForbidden();
        $admin = $this->user('admin');
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/imports')->assertOk()->assertJsonPath('sources.0.key', 'kktcarabam');

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/imports/sources/{$this->source->id}/runs", ['mode' => 'dry_run', 'target' => 10])->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/imports/sources/{$this->source->id}/runs", ['mode' => 'dry_run'])->assertStatus(202);
        Queue::assertPushed(RunSourceImport::class);
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/imports/sources/{$this->source->id}/runs", ['mode' => 'dry_run'])->assertStatus(409);

        $this->runImport('import', KktFixture::adapter(KktFixture::pool(20)));
        $sl = SourceListing::whereNotNull('listing_id')->firstOrFail();
        $detail = $this->actingAs($admin, 'sanctum')->getJson("/api/v1/admin/imports/listings/{$sl->id}")->assertOk()->json('source_listing');
        $this->assertArrayHasKey('source_description', $detail);
        $preview = $detail['images'][0]['thumb_url'];
        $this->get($preview)->assertOk();
        $this->get(strtok($preview, '?'))->assertForbidden();
    }

    public function test_queue_jobs_have_explicit_retry_policies(): void
    {
        $recheck = new RecheckSourceListing('x');
        $this->assertSame(3, $recheck->tries);
        $this->assertSame([60, 300, 900], $recheck->backoff());
        $this->assertSame('crawl', $recheck->queue);
        $this->assertSame(1, (new RunSourceImport('x'))->tries, 'runs against an external site are never blindly retried');
    }

    public function test_live_adapter_stops_on_challenge_and_respects_robots(): void
    {
        $page = KktFixture::listingHtml(['id' => '272325']);
        Http::fake([
            'www.kktcarabam.com/robots.txt' => Http::response("User-agent: *\nAllow: /\nDisallow: /sayfa/\n"),
            'www.kktcarabam.com/272325-*' => Http::response($page),
            'www.kktcarabam.com/272326-*' => Http::response('<title>Just a moment...</title>', 403),
        ]);
        $adapter = app(KktcarabamAdapter::class)->withDelay(0);
        $ok = $adapter->fetchListing('https://www.kktcarabam.com/272325-toyota-corolla');
        $this->assertSame('ok', $ok['status']);
        $this->assertSame('Toyota', $ok['mapped']['data']['make']);
        $this->assertSame('blocked', $adapter->fetchListing('https://www.kktcarabam.com/272326-toyota-corolla')['status']);
        // After a block the adapter refuses further requests in the same run.
        $this->assertSame('blocked', $adapter->fetchListing('https://www.kktcarabam.com/272325-toyota-corolla')['status']);
        Http::assertSent(fn ($req) => str_contains($req->header('User-Agent')[0] ?? '', 'AIPriceListingImporter'));
    }
}
