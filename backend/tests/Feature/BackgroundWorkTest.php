<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeListingPrice;
use App\Jobs\CrawlCompetitorSource;
use App\Models\CompetitorSource;
use App\Models\Listing;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\KycReviewed;
use App\Notifications\ListingReviewed;
use App\Notifications\WelcomeVerifyEmail;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackgroundWorkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function staff(string $role = 'super_admin'): User
    {
        $u = User::factory()->create(['role' => $role]);
        $u->assignRole($role);
        return $u;
    }

    private function listing(array $attrs = []): Listing
    {
        $owner = User::factory()->create(['role' => 'seller']);
        $owner->assignRole('seller');

        return Listing::create($attrs + [
            'user_id' => $owner->id,
            'type' => 'vehicle',
            'listable_type' => 'App\\Models\\Vehicle',
            'listable_id' => (string) Str::uuid(),
            'title' => 'Test car',
            'slug' => 'test-car-'.Str::random(6),
            'price' => 10000,
            'currency' => 'GBP',
            'status' => 'pending',
        ]);
    }

    public function test_approving_queues_analysis_and_notifies_the_owner(): void
    {
        Queue::fake();
        Notification::fake();
        $listing = $this->listing();

        $this->actingAs($this->staff(), 'sanctum')
            ->postJson("/api/v1/admin/listings/{$listing->id}/approve")
            ->assertOk();

        Queue::assertPushed(AnalyzeListingPrice::class, fn ($job) => $job->listingId === $listing->id);
        Notification::assertSentTo($listing->user, ListingReviewed::class, fn ($n) => $n->approved === true);
        $this->assertSame('active', $listing->fresh()->status);
    }

    public function test_rejecting_notifies_with_reason(): void
    {
        Notification::fake();
        $listing = $this->listing();

        $this->actingAs($this->staff(), 'sanctum')
            ->postJson("/api/v1/admin/listings/{$listing->id}/reject", ['reason' => 'Photos missing'])
            ->assertOk();

        Notification::assertSentTo($listing->user, ListingReviewed::class,
            fn ($n) => $n->approved === false && $n->reason === 'Photos missing');
    }

    public function test_batch_analyze_and_crawls_are_queued_not_run_inline(): void
    {
        Queue::fake();
        $this->listing(['status' => 'active']);
        $this->listing(['status' => 'active']);
        CompetitorSource::create(['name' => 'A', 'url' => 'https://a.example', 'type' => 'vehicle', 'is_active' => true]);
        CompetitorSource::create(['name' => 'B', 'url' => 'https://b.example', 'type' => 'vehicle', 'is_active' => false]);

        // The seeder ships its own sources; only active ones may be crawled.
        $active = CompetitorSource::where('is_active', true)->count();

        $this->actingAs($this->staff(), 'sanctum');
        $this->postJson('/api/v1/admin/listings/batch-analyze')->assertStatus(202)->assertJsonPath('queued', 2);
        $this->postJson('/api/v1/admin/competitor-sources/import-all')->assertStatus(202)->assertJsonPath('queued', $active);

        Queue::assertPushed(AnalyzeListingPrice::class, 2);
        Queue::assertPushed(CrawlCompetitorSource::class, $active);
        Queue::assertNotPushed(CrawlCompetitorSource::class, fn ($job) => CompetitorSource::find($job->sourceId)?->name === 'B');
    }

    public function test_kyc_decisions_notify_the_user(): void
    {
        Notification::fake();
        $buyer = User::factory()->create(['role' => 'buyer']);
        $buyer->assignRole('buyer');
        $this->actingAs($buyer, 'sanctum')->postJson('/api/v1/kyc', ['document_type' => 'passport', 'document_number' => 'X1']);

        $this->actingAs($this->staff(), 'sanctum')->postJson("/api/v1/admin/users/{$buyer->id}/approve-kyc")->assertOk();

        Notification::assertSentTo($buyer, KycReviewed::class, fn ($n) => $n->approved === true);
    }

    public function test_registration_sends_verification_and_link_verifies(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'New', 'email' => 'new@example.com', 'password' => 'Secret@123', 'password_confirmation' => 'Secret@123',
        ])->assertCreated()->assertJsonPath('user.email_verified', false);

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $link = null;
        Notification::assertSentTo($user, WelcomeVerifyEmail::class, function ($n) use ($user, &$link) {
            $link = $n->toMail($user)->actionUrl;
            return true;
        });

        $this->assertStringStartsWith(config('app.frontend_url').'/verify-email?', $link);

        // The SPA forwards the query string to the API route.
        parse_str(parse_url($link, PHP_URL_QUERY), $q);
        $this->getJson("/api/v1/auth/email/verify/{$q['id']}/{$q['hash']}?expires={$q['expires']}&signature={$q['signature']}")
            ->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_tampered_verification_link_is_refused(): void
    {
        $user = User::factory()->unverified()->create();

        $this->getJson("/api/v1/auth/email/verify/{$user->id}/".sha1($user->email).'?expires=9999999999&signature=forged')
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_users_cannot_publish_or_inquire(): void
    {
        $user = User::factory()->unverified()->create(['role' => 'seller']);
        $user->assignRole('seller');
        $listing = $this->listing(['status' => 'active']);

        $this->actingAs($user, 'sanctum');
        $this->postJson('/api/v1/listings/create', ['type' => 'vehicle'])
            ->assertForbidden()->assertJsonPath('message', 'Your email address is not verified.');
        $this->postJson("/api/v1/listings/{$listing->id}/inquire", ['message' => 'hi'])
            ->assertForbidden()->assertJsonPath('message', 'Your email address is not verified.');
    }

    public function test_inquiry_alerts_support_staff(): void
    {
        Notification::fake();
        $support = $this->staff('support');
        $moderator = $this->staff('moderator');
        $buyer = User::factory()->create(['role' => 'buyer']);
        $buyer->assignRole('buyer');
        $listing = $this->listing(['status' => 'active']);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/v1/listings/{$listing->id}/inquire", ['message' => 'Is it available?'])
            ->assertCreated();

        Notification::assertSentTo($support, \App\Notifications\NewInquiryForStaff::class);
        Notification::assertNotSentTo($moderator, \App\Notifications\NewInquiryForStaff::class);
    }

    public function test_expiry_commands(): void
    {
        $stale = $this->listing(['status' => 'active', 'expires_at' => now()->subDay()]);
        $fresh = $this->listing(['status' => 'active', 'expires_at' => now()->addDay()]);

        $plan = SubscriptionPlan::first();
        $owner = User::factory()->create();
        $ended = Subscription::create(['user_id' => $owner->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'amount' => 10, 'currency' => 'GBP', 'current_period_end' => now()->subHour()]);
        $running = Subscription::create(['user_id' => $owner->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'amount' => 10, 'currency' => 'GBP', 'current_period_end' => now()->addWeek()]);

        $this->artisan('listings:expire')->assertSuccessful();
        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame('expired', $stale->fresh()->status);
        $this->assertSame('active', $fresh->fresh()->status);
        $this->assertSame('expired', $ended->fresh()->status);
        $this->assertSame('active', $running->fresh()->status);
    }

    public function test_cancelling_keeps_access_until_period_end(): void
    {
        $plan = SubscriptionPlan::first();
        $user = User::factory()->create();
        $sub = Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'amount' => 10, 'currency' => 'GBP', 'current_period_end' => now()->addWeek()]);

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/my-subscription/{$sub->id}/cancel")->assertOk();
        $this->assertSame('active', $sub->fresh()->status);
        $this->assertNotNull($sub->fresh()->cancelled_at);

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/my-subscription/{$sub->id}/cancel")->assertStatus(422);
    }

    public function test_user_notification_inbox(): void
    {
        $user = User::factory()->create();
        $user->notify(new KycReviewed(approved: true));
        $user->notify(new KycReviewed(approved: false, reason: 'blurry'));

        $this->actingAs($user, 'sanctum');
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('unread_count', 2);

        $id = $user->notifications()->first()->id;
        $this->postJson("/api/v1/notifications/{$id}/read")->assertJsonPath('unread_count', 1);
        $this->postJson('/api/v1/notifications/read-all')->assertJsonPath('unread_count', 0);

        // Another user's notification id is not reachable.
        $other = User::factory()->create();
        $other->notify(new KycReviewed(approved: true));
        $this->postJson('/api/v1/notifications/'.$other->notifications()->first()->id.'/read')->assertNotFound();
    }
}
