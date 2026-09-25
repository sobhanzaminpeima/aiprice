<?php

namespace Tests\Feature;

use App\Jobs\SendAnnouncement;
use App\Models\Announcement;
use App\Models\BlogPost;
use App\Models\FraudReport;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Notifications\SupportTicketUpdated;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function user(string $role): User
    {
        $u = User::factory()->create(['role' => $role]);
        $u->assignRole($role);
        return $u;
    }

    private function listing(array $attrs = []): Listing
    {
        $owner = $attrs['owner'] ?? $this->user('seller');
        unset($attrs['owner']);

        return Listing::create($attrs + [
            'user_id' => $owner->id, 'type' => 'vehicle', 'listable_type' => 'App\\Models\\Vehicle',
            'listable_id' => (string) Str::uuid(), 'title' => 'Car '.Str::random(4), 'slug' => 'car-'.Str::random(8),
            'price' => 10000, 'currency' => 'GBP', 'status' => 'active', 'city' => 'Girne',
        ]);
    }

    public function test_every_module_endpoint_responds_for_super_admin(): void
    {
        $this->actingAs($this->user('super_admin'), 'sanctum');

        foreach ([
            '/admin/financial/overview', '/admin/financial/payments', '/admin/market/overview', '/admin/live/snapshot',
            '/admin/fraud/overview', '/admin/fraud/reports', '/admin/support/tickets', '/admin/support/agents',
            '/admin/reports', '/admin/content/posts', '/admin/content/categories', '/admin/content/faqs',
            '/admin/announcements',
        ] as $uri) {
            $this->getJson('/api/v1'.$uri)->assertOk();
        }
    }

    public function test_finance_overview_uses_real_payments(): void
    {
        $buyer = $this->user('buyer');
        Payment::create(['user_id' => $buyer->id, 'amount' => 49.99, 'currency' => 'GBP', 'status' => 'completed', 'gateway' => 'stripe']);
        Payment::create(['user_id' => $buyer->id, 'amount' => 20, 'currency' => 'GBP', 'status' => 'failed', 'gateway' => 'stripe']);

        $this->actingAs($this->user('finance_manager'), 'sanctum')
            ->getJson('/api/v1/admin/financial/overview')
            ->assertOk()
            ->assertJsonPath('stats.revenue_this_month', 49.99)
            ->assertJsonPath('stats.failed_payments_this_month', 1);
    }

    public function test_reporting_a_listing_captures_risk_and_blocks_duplicates(): void
    {
        $listing = $this->listing();
        $reporter = $this->user('buyer');
        $this->actingAs($reporter, 'sanctum');

        $this->postJson("/api/v1/listings/{$listing->id}/report", ['reason' => 'Scam'])->assertCreated();
        $this->postJson("/api/v1/listings/{$listing->id}/report", ['reason' => 'Scam'])->assertOk(); // idempotent
        $this->assertSame(1, FraudReport::count());
        $this->assertNotNull(FraudReport::first()->risk_score);

        $this->postJson('/api/v1/listings/'.Str::uuid().'/report', ['reason' => 'x'])->assertNotFound();
        $this->actingAs($listing->user, 'sanctum')->postJson("/api/v1/listings/{$listing->id}/report", ['reason' => 'x'])->assertStatus(422);
    }

    public function test_confirming_fraud_takes_the_listing_down(): void
    {
        $listing = $this->listing();
        $a = FraudReport::create(['listing_id' => $listing->id, 'reported_by' => $this->user('buyer')->id, 'reason' => 'Scam', 'status' => 'pending']);
        $b = FraudReport::create(['listing_id' => $listing->id, 'reported_by' => $this->user('buyer')->id, 'reason' => 'Fake', 'status' => 'pending']);

        $this->actingAs($this->user('moderator'), 'sanctum')
            ->putJson("/api/v1/admin/fraud/reports/{$a->id}", ['status' => 'confirmed'])
            ->assertOk();

        $this->assertSame('archived', $listing->fresh()->status);
        $this->assertSame('confirmed', $b->fresh()->status);
    }

    public function test_support_ticket_round_trip_notifies_both_sides(): void
    {
        Notification::fake();
        $customer = $this->user('buyer');
        $agent = $this->user('support');

        $ticketId = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/support-tickets', ['subject' => 'Cannot upload', 'description' => 'It fails', 'category' => 'technical'])
            ->assertCreated()->json('id');
        Notification::assertSentTo($agent, SupportTicketUpdated::class, fn ($n) => $n->event === 'opened');

        $this->actingAs($agent, 'sanctum')
            ->postJson("/api/v1/admin/support/tickets/{$ticketId}/reply", ['body' => 'Try again now'])
            ->assertCreated();
        Notification::assertSentTo($customer, SupportTicketUpdated::class, fn ($n) => $n->event === 'staff_reply');
        $this->assertSame('waiting', SupportTicket::find($ticketId)->status);

        // Customer replying re-opens it.
        $this->actingAs($customer, 'sanctum')->postJson("/api/v1/support-tickets/{$ticketId}/reply", ['body' => 'Works, thanks'])->assertCreated();
        $this->assertSame('open', SupportTicket::find($ticketId)->status);

        // Another customer cannot see it.
        $this->actingAs($this->user('buyer'), 'sanctum')->getJson("/api/v1/support-tickets/{$ticketId}")->assertNotFound();
    }

    public function test_export_neutralises_formula_injection_and_checks_data_permission(): void
    {
        $this->listing(['title' => '=HYPERLINK("http://evil","x")']);

        $csv = $this->actingAs($this->user('super_admin'), 'sanctum')
            ->get('/api/v1/admin/reports/listings/export')
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);

        // ai_analyst has view_analytics but not manage_users: the users export is refused.
        $this->actingAs($this->user('ai_analyst'), 'sanctum')->get('/api/v1/admin/reports/users/export')->assertForbidden();
    }

    public function test_blog_publishing_and_public_visibility(): void
    {
        $this->actingAs($this->user('super_admin'), 'sanctum');
        $draft = $this->postJson('/api/v1/admin/content/posts', ['title' => 'Draft post', 'content' => 'x'])->assertCreated()->json();
        $live = $this->postJson('/api/v1/admin/content/posts', ['title' => 'Live post', 'content' => 'x', 'status' => 'published'])->assertCreated()->json();
        $this->postJson('/api/v1/admin/content/posts', ['title' => 'Live post', 'content' => 'y', 'status' => 'published'])
            ->assertCreated()->assertJsonPath('slug', 'live-post-2');
        $this->postJson('/api/v1/admin/content/posts', ['title' => 'Later', 'content' => 'x', 'status' => 'scheduled', 'published_at' => now()->addDay()->toIso8601String()])->assertCreated();

        $this->app['auth']->forgetGuards();
        $slugs = collect($this->getJson('/api/v1/blog')->assertOk()->json('data'))->pluck('slug');
        $this->assertTrue($slugs->contains('live-post'));
        $this->assertFalse($slugs->contains($draft['slug']));
        $this->assertFalse($slugs->contains('later'));
        $this->getJson("/api/v1/blog/{$draft['slug']}")->assertNotFound();
        $this->getJson("/api/v1/blog/{$live['slug']}")->assertOk();
        $this->assertSame(1, BlogPost::where('slug', 'live-post')->value('view_count'));
    }

    public function test_announcement_reaches_only_its_audience(): void
    {
        Notification::fake();
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');

        Queue::fake();
        $this->actingAs($this->user('super_admin'), 'sanctum')
            ->postJson('/api/v1/admin/announcements', ['title' => 'New fees', 'body' => 'Details', 'audience' => 'sellers'])
            ->assertStatus(202);
        Queue::assertPushed(SendAnnouncement::class);

        (new SendAnnouncement(Announcement::first()))->handle();

        Notification::assertSentTo($seller, AnnouncementNotification::class);
        Notification::assertNotSentTo($buyer, AnnouncementNotification::class);
        $this->assertSame('sent', Announcement::first()->status);
    }

    public function test_announcements_need_manage_cms(): void
    {
        $this->actingAs($this->user('support'), 'sanctum')
            ->postJson('/api/v1/admin/announcements', ['title' => 'x', 'body' => 'y', 'audience' => 'all'])
            ->assertForbidden();
    }
}
