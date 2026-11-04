<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Notifications\NewInquiryForSeller;
use App\Services\SellerContactService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SellerContactTest extends TestCase
{
    use RefreshDatabase;

    private function activeListing(User $seller): Listing
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $seller->assignRole('seller');
        $id = $this->actingAs($seller, 'sanctum')->postJson('/api/v1/listings/create', [
            'type' => 'vehicle', 'brand_name' => 'Toyota', 'model_name' => 'Corolla', 'year' => 2020,
            'title' => 'Contact test car', 'price' => 15000, 'currency' => 'GBP', 'city' => 'Kyrenia',
        ])->assertCreated()->json('listing.id');
        $listing = Listing::findOrFail($id);
        $listing->update(['status' => 'active', 'published_at' => now()]);
        $this->app['auth']->forgetGuards();

        return $listing;
    }

    public function test_mask_hides_last_three_digits_and_keeps_formatting(): void
    {
        $this->assertSame('+90 533 812 3•••', SellerContactService::mask('+90 533 812 3456'));
    }

    public function test_guests_only_ever_receive_the_masked_number(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'name' => 'Ayse Demir', 'phone' => '+90 533 812 3456']);
        $listing = $this->activeListing($seller);

        $res = $this->getJson('/api/v1/listings/'.$listing->slug)->assertOk()
            ->assertJsonPath('seller_contact.phone_masked', '+90 533 812 3•••')
            ->assertJsonPath('seller_contact.display_name', 'Ayse')
            ->assertJsonMissingPath('listing.user');
        $this->assertStringNotContainsString('3456', $res->getContent());
        $this->assertStringNotContainsString('Demir', $res->getContent());

        $this->getJson("/api/v1/listings/{$listing->id}/seller-phone")->assertUnauthorized();
    }

    public function test_listing_meta_with_source_and_contact_is_never_serialized(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $listing = $this->activeListing($seller);
        $listing->update(['meta' => ['source_url' => 'https://www.kktcarabam.com/1-x', 'contact_phone' => '+90 533 000 9999'], 'import_source_key' => 'kktcarabam:1']);

        $body = $this->getJson('/api/v1/listings/'.$listing->slug)->assertOk()->getContent()
            .$this->getJson('/api/v1/listings?type=vehicle')->assertOk()->getContent();
        $this->assertStringNotContainsString('kktcarabam', $body);
        $this->assertStringNotContainsString('0009999', $body);

        // The vehicle row carries its own meta (import provenance): also never public.
        $listing->listable->update(['meta' => ['source_url' => 'https://www.kktcarabam.com/2-y']]);
        $this->assertStringNotContainsString('kktcarabam', $this->getJson('/api/v1/listings/'.$listing->slug)->assertOk()->getContent());
    }

    public function test_signed_in_users_get_the_full_number_and_the_reveal_is_logged(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'phone' => '+90 533 812 3456']);
        $listing = $this->activeListing($seller);
        $buyer = User::factory()->create();

        $this->actingAs($buyer, 'sanctum')->getJson("/api/v1/listings/{$listing->id}/seller-phone")
            ->assertOk()->assertJsonPath('phone', '+90 533 812 3456')->assertJsonPath('whatsapp', '905338123456');
        $this->assertDatabaseHas('listing_contact_reveals', ['listing_id' => $listing->id, 'user_id' => $buyer->id]);
    }

    public function test_send_to_seller_notifies_the_seller_and_shares_contact_only_on_opt_in(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $listing = $this->activeListing($seller);
        Notification::fake();
        $buyer = User::factory()->create(['name' => 'Buyer One', 'phone' => '+90 548 000 1122']);

        $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/listings/{$listing->id}/inquire", [
            'message' => 'Is it still available?', 'share_contact' => true,
        ])->assertCreated();
        $this->actingAs($buyer, 'sanctum')->postJson("/api/v1/listings/{$listing->id}/inquire", [
            'message' => 'Second message, private.',
        ])->assertCreated();
        Notification::assertSentTo($seller, NewInquiryForSeller::class);

        $rows = collect($this->actingAs($seller, 'sanctum')->getJson('/api/v1/my-listing-inquiries')->assertOk()->json());
        $this->assertSame('+90 548 000 1122', $rows->firstWhere('share_contact', true)['buyer']['phone']);
        $this->assertNull($rows->firstWhere('share_contact', false)['buyer']);

        $this->actingAs($seller, 'sanctum')->postJson("/api/v1/listings/{$listing->id}/inquire", ['message' => 'Messaging myself'])
            ->assertStatus(422);
    }
}
