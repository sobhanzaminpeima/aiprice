<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeListingPrice;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MarketplaceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public static function markets(): array
    {
        return [
            'vehicle' => [['type' => 'vehicle', 'brand_name' => 'BMW', 'model_name' => 'X5', 'year' => 2022, 'mileage' => 32000]],
            'property' => [['type' => 'property', 'property_type_name' => 'Villa', 'bedrooms' => 3, 'area_sqm' => 180]],
        ];
    }

    #[DataProvider('markets')]
    public function test_seller_submission_admin_approval_and_buyer_inquiry(array $attributes): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        Notification::fake();
        $seller = User::factory()->create(['role' => 'seller']);
        $seller->assignRole('seller');
        $payload = $attributes + ['title' => 'Workflow listing', 'price' => 45000, 'currency' => 'GBP', 'city' => 'Kyrenia'];
        $id = $this->actingAs($seller, 'sanctum')->postJson('/api/v1/listings/create', $payload)
            ->assertCreated()->assertJsonPath('listing.status', 'pending')->json('listing.id');
        $listing = Listing::findOrFail($id);
        $buyer = User::factory()->create();
        $this->actingAs($buyer, 'sanctum');
        $this->getJson('/api/v1/listings/'.$listing->slug)->assertNotFound();
        $this->getJson("/api/v1/listings/{$id}/better")->assertNotFound();
        $this->postJson("/api/v1/listings/{$id}/inquire", ['message' => 'Can I arrange a viewing?'])->assertNotFound();
        $admin = User::where('role', 'super_admin')->firstOrFail();
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/listings/{$id}/approve")->assertOk();
        Queue::assertPushed(AnalyzeListingPrice::class, fn ($job) => $job->listingId === $id);
        $this->assertSame('45000.00', $listing->fresh()->price);
        $this->actingAs($buyer, 'sanctum');
        $this->getJson('/api/v1/listings/'.$listing->slug)->assertOk()->assertJsonPath('listing.id', $id)->assertJsonMissingPath('listing.documents');
        $this->getJson('/api/v1/listings?type='.$attributes['type'].'&search=Workflow')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->postJson("/api/v1/listings/{$id}/inquire", ['message' => 'Can I arrange a viewing?'])->assertCreated();
        $this->assertDatabaseHas('inquiries', ['listing_id' => $id, 'buyer_id' => $buyer->id]);
    }

    public function test_pagination_rejects_unbounded_requests(): void
    {
        $this->getJson('/api/v1/listings?per_page=100000')->assertUnprocessable();
        $this->getJson('/api/v1/listings?per_page=0')->assertUnprocessable();
        $this->getJson('/api/v1/listings?per_page=12&mode=deal')->assertOk();
    }
}
