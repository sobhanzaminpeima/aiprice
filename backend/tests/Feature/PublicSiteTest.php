<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketUpdated;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function listing(): Listing
    {
        $owner = User::factory()->create(['role' => 'seller']);
        return Listing::create([
            'user_id' => $owner->id, 'type' => 'vehicle', 'listable_type' => 'App\\Models\\Vehicle',
            'listable_id' => (string) Str::uuid(), 'title' => 'Test car', 'slug' => 'test-car-'.Str::random(5),
            'price' => 10000, 'currency' => 'GBP', 'status' => 'active',
        ]);
    }

    public function test_contact_form_creates_guest_ticket_and_staff_reply_emails_the_guest(): void
    {
        Notification::fake();
        $agent = User::factory()->create(['role' => 'support']);
        $agent->assignRole('support');

        $ref = $this->postJson('/api/v1/contact', ['name' => 'Ayşe', 'email' => 'ayse@example.com', 'message' => 'Is the villa still available?'])
            ->assertCreated()->json('reference');

        $ticket = SupportTicket::where('ticket_number', $ref)->firstOrFail();
        $this->assertNull($ticket->user_id);
        $this->assertSame('contact_form', $ticket->source);
        Notification::assertSentTo($agent, SupportTicketUpdated::class);

        $this->actingAs($agent, 'sanctum')->postJson("/api/v1/admin/support/tickets/{$ticket->id}/reply", ['body' => 'Yes it is!'])->assertCreated();

        Notification::assertSentOnDemand(SupportTicketUpdated::class, function ($n, $channels, AnonymousNotifiable $notifiable) {
            return $channels === ['mail'] && array_key_exists('ayse@example.com', $notifiable->routes['mail']);
        });
    }

    public function test_contact_form_rejects_bots_and_short_messages(): void
    {
        $this->postJson('/api/v1/contact', ['name' => 'Bot', 'email' => 'b@example.com', 'message' => 'buy cheap things now', 'website' => 'spam.biz'])->assertStatus(422);
        $this->postJson('/api/v1/contact', ['name' => 'X', 'email' => 'x@example.com', 'message' => 'hi'])->assertStatus(422);
        $this->assertSame(0, SupportTicket::count());
    }

    public function test_sitemap_lists_active_listings_only(): void
    {
        $live = $this->listing();
        $hidden = $this->listing();
        $hidden->update(['status' => 'pending']);

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

        $this->assertStringContainsString("/listings/{$live->fresh()->slug}", $xml);
        $this->assertStringNotContainsString("/listings/{$hidden->fresh()->slug}<", $xml);
        $this->assertStringContainsString('/pricing', $xml);
    }

    public function test_repeat_views_from_the_same_visitor_count_once(): void
    {
        $l = $this->listing();

        $this->getJson("/api/v1/listings/{$l->slug}")->assertOk();
        $this->getJson("/api/v1/listings/{$l->slug}")->assertOk();
        $this->assertSame(1, $l->fresh()->view_count);

        $this->travel(31)->minutes();
        $this->getJson("/api/v1/listings/{$l->slug}")->assertOk();
        $this->assertSame(2, $l->fresh()->view_count);
    }

    public function test_public_market_stats_expose_aggregates_only(): void
    {
        $this->listing();
        $json = $this->getJson('/api/v1/market/stats')->assertOk()->assertJsonStructure(['indexes', 'price_verdicts', 'ai_scored_listings'])->json();
        $this->assertArrayNotHasKey('competitors', $json);
    }
}
