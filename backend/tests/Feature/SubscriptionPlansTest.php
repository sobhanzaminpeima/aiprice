<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\PaymentSucceeded;
use App\Notifications\SubscriptionUpdated;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Launch pricing: plan ladders, limits, the free first month and manual payments. */
class SubscriptionPlansTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        Notification::fake();
        // No online gateway: the launch setup.
        config(['services.stripe.secret' => null, 'services.stripe.webhook_secret' => null]);
    }

    private function seller(string $role = 'seller'): User
    {
        $u = User::factory()->create(['role' => $role]);
        $u->assignRole($role);
        return $u;
    }

    private function admin(): User
    {
        return User::where('role', 'super_admin')->firstOrFail();
    }

    private function plan(string $slug): SubscriptionPlan
    {
        return SubscriptionPlan::where('slug', $slug)->firstOrFail();
    }

    private function listingPayload(int $i = 1): array
    {
        return ['type' => 'vehicle', 'brand_name' => 'Toyota', 'model_name' => 'Corolla', 'year' => 2019,
            'title' => "Car {$i}", 'price' => 9000 + $i, 'currency' => 'GBP', 'city' => 'Kyrenia'];
    }

    public function test_public_plans_are_grouped_by_audience_with_launch_prices(): void
    {
        $plans = collect($this->getJson('/api/v1/subscription-plans')->assertOk()->json());

        $this->assertSame(['private-free'], $plans->where('type', 'seller')->pluck('slug')->values()->all());
        $this->assertEqualsCanonicalizing(['dealer-start', 'dealer-pro', 'dealer-max'], $plans->where('type', 'dealer')->pluck('slug')->all());
        $this->assertEqualsCanonicalizing(['agency-start', 'agency-pro', 'agency-elite'], $plans->where('type', 'agency')->pluck('slug')->all());

        $pro = $plans->firstWhere('slug', 'dealer-pro');
        $this->assertEquals(49, $pro['price_monthly']);
        $this->assertEquals(490, $pro['price_yearly']); // two months free
        $this->assertSame(30, $pro['trial_days']);
        $this->assertTrue($pro['is_highlighted']);
        // The pre-launch generic plans are no longer on sale.
        $this->assertNull($plans->firstWhere('slug', 'starter'));
    }

    public function test_free_plan_limits_active_listings_and_sold_ones_free_a_slot(): void
    {
        $seller = $this->seller();
        $this->actingAs($seller, 'sanctum');

        $first = $this->postJson('/api/v1/listings/create', $this->listingPayload(1))->assertCreated()->json('listing.id');
        $this->postJson('/api/v1/listings/create', $this->listingPayload(2))->assertCreated();
        $this->postJson('/api/v1/listings/create', $this->listingPayload(3))
            ->assertStatus(422)->assertJsonValidationErrors('plan');

        $this->getJson('/api/v1/my-subscription')->assertOk()
            ->assertJsonPath('usage.listings', 2)->assertJsonPath('usage.listing_limit', 2)
            ->assertJsonPath('trial_eligible', true);

        $this->postJson("/api/v1/listings/{$first}/close", ['status' => 'sold'])->assertOk()->assertJsonPath('usage.listings', 1);
        $this->postJson('/api/v1/listings/create', $this->listingPayload(4))->assertCreated();

        // Relisting the sold one needs a free slot too.
        $this->putJson("/api/v1/listings/{$first}", ['price' => 8500])->assertStatus(422)->assertJsonValidationErrors('plan');
    }

    public function test_legacy_store_endpoint_is_also_limited(): void
    {
        $seller = $this->seller();
        foreach ([1, 2] as $i) {
            Listing::create(['user_id' => $seller->id, 'type' => 'vehicle', 'title' => "L{$i}", 'price' => 1, 'currency' => 'GBP', 'status' => 'active',
                'listable_type' => \App\Models\Vehicle::class, 'listable_id' => (string) \Illuminate\Support\Str::uuid()]);
        }
        $this->actingAs($seller, 'sanctum')->postJson('/api/v1/listings', ['type' => 'rental', 'title' => 'Flat', 'price' => 500, 'rental_type' => 'monthly'])
            ->assertStatus(422)->assertJsonValidationErrors('plan');
    }

    public function test_first_paid_plan_starts_a_free_month_once(): void
    {
        $dealer = $this->seller('dealer');
        $this->actingAs($dealer, 'sanctum');

        $this->postJson('/api/v1/billing/subscribe', ['plan_id' => $this->plan('dealer-pro')->id, 'cycle' => 'monthly'])
            ->assertCreated()->assertJsonPath('mode', 'trial')->assertJsonPath('subscription.status', 'trial');

        $sub = Subscription::where('user_id', $dealer->id)->sole();
        $this->assertTrue($sub->current_period_end->between(now()->addDays(29), now()->addDays(31)));
        $this->assertNotNull($dealer->fresh()->trial_used_at);

        $me = $this->getJson('/api/v1/my-subscription')->assertOk();
        $me->assertJsonPath('is_trial', true)->assertJsonPath('usage.listing_limit', 60)->assertJsonPath('trial_eligible', false);

        // Switching during the trial keeps the same end date.
        $this->postJson('/api/v1/billing/subscribe', ['plan_id' => $this->plan('dealer-max')->id, 'cycle' => 'monthly'])
            ->assertOk()->assertJsonPath('mode', 'trial');
        $this->assertEquals($sub->current_period_end->timestamp, $sub->fresh()->current_period_end->timestamp);
        $this->assertSame($this->plan('dealer-max')->id, $sub->fresh()->plan_id);

        // After the trial ends there is no second trial: the request waits for payment.
        $this->travel(31)->days();
        $this->artisan('subscriptions:expire')->assertSuccessful();
        Notification::assertSentTo($dealer, SubscriptionUpdated::class, fn ($n) => $n->event === 'ended');
        $this->getJson('/api/v1/my-subscription')->assertJsonPath('is_active', false)->assertJsonPath('usage.listing_limit', 2);

        $this->postJson('/api/v1/billing/subscribe', ['plan_id' => $this->plan('dealer-pro')->id, 'cycle' => 'monthly'])
            ->assertCreated()->assertJsonPath('mode', 'manual')->assertJsonPath('subscription.status', 'pending');
    }

    public function test_admin_records_a_manual_payment_which_activates_the_plan_after_the_trial(): void
    {
        $agency = $this->seller('agency');
        $this->actingAs($agency, 'sanctum')->postJson('/api/v1/billing/subscribe', ['plan_id' => $this->plan('agency-pro')->id, 'cycle' => 'monthly'])
            ->assertJsonPath('mode', 'trial');
        $trialEnd = Subscription::where('user_id', $agency->id)->sole()->current_period_end;

        // The seller asks to keep the plan before the trial ends.
        $pending = $this->postJson('/api/v1/billing/subscribe', ['plan_id' => $this->plan('agency-pro')->id, 'cycle' => 'yearly', 'pay_now' => true])
            ->assertCreated()->assertJsonPath('mode', 'manual')->json('subscription.id');
        $this->getJson('/api/v1/my-subscription')->assertJsonPath('is_trial', true)->assertJsonPath('pending.id', $pending);

        $this->actingAs($this->admin(), 'sanctum');
        $this->getJson('/api/v1/admin/subscriptions?status=pending')->assertOk()->assertJsonPath('data.0.id', $pending);
        $this->postJson("/api/v1/admin/subscriptions/{$pending}/record-payment", ['billing_cycle' => 'yearly', 'reference' => 'IBAN-123'])
            ->assertOk()->assertJsonPath('status', 'active');

        $sub = Subscription::findOrFail($pending);
        // Paying early doesn't cost trial days: the year starts when the trial ends.
        $this->assertEquals($trialEnd->copy()->addYear()->toDateString(), $sub->current_period_end->toDateString());
        $this->assertSame(1, Subscription::where('user_id', $agency->id)->whereIn('status', Subscription::LIVE)->count());

        $payment = Payment::where('subscription_id', $pending)->sole();
        $this->assertSame('manual', $payment->gateway);
        $this->assertEquals(690, $payment->amount);
        $this->assertSame('paid', Invoice::where('payment_id', $payment->id)->sole()->status);
        Notification::assertSentTo($agency, PaymentSucceeded::class);

        $stats = $this->getJson('/api/v1/admin/subscriptions/stats')->assertOk()->json();
        $this->assertEquals(57.5, $stats['mrr']);
        $this->assertSame(0, $stats['pending']);
    }

    public function test_featured_slots_follow_the_plan(): void
    {
        $dealer = $this->seller('dealer');
        $listings = collect(range(1, 3))->map(fn ($i) => Listing::create([
            'user_id' => $dealer->id, 'type' => 'vehicle', 'title' => "L{$i}", 'price' => 1, 'currency' => 'GBP', 'status' => 'active',
            'listable_type' => \App\Models\Vehicle::class, 'listable_id' => (string) \Illuminate\Support\Str::uuid(),
        ]));
        $this->actingAs($dealer, 'sanctum');

        // Free plan: no featured slots.
        $this->postJson("/api/v1/listings/{$listings[0]->id}/feature")->assertStatus(422);

        $this->actingAs($this->admin(), 'sanctum')->postJson('/api/v1/admin/subscriptions', [
            'email' => $dealer->email, 'plan_id' => $this->plan('dealer-pro')->id, 'months' => 1,
        ])->assertCreated();
        $this->plan('dealer-pro')->update(['max_featured' => 2]);

        $this->actingAs($dealer, 'sanctum');
        $this->postJson("/api/v1/listings/{$listings[0]->id}/feature")->assertOk()->assertJsonPath('is_featured', true);
        $this->postJson("/api/v1/listings/{$listings[1]->id}/feature")->assertOk();
        $this->postJson("/api/v1/listings/{$listings[2]->id}/feature")->assertStatus(422);
        $this->postJson("/api/v1/listings/{$listings[0]->id}/feature")->assertOk()->assertJsonPath('is_featured', false);
    }

    public function test_approval_uses_the_plan_listing_duration(): void
    {
        $seller = $this->seller();
        $id = $this->actingAs($seller, 'sanctum')->postJson('/api/v1/listings/create', $this->listingPayload())->json('listing.id');
        $this->actingAs($this->admin(), 'sanctum')->postJson("/api/v1/admin/listings/{$id}/approve")->assertOk();

        $this->assertEquals(now()->addDays(60)->toDateString(), Listing::findOrFail($id)->expires_at->toDateString());
    }

    public function test_admin_manages_plans_and_payment_instructions(): void
    {
        $this->actingAs($this->admin(), 'sanctum');

        $id = $this->postJson('/api/v1/admin/subscription-plans', [
            'name' => 'Launch Partner', 'type' => 'dealer', 'price_monthly' => 9, 'max_listings' => 30, 'trial_days' => 30,
        ])->assertCreated()->assertJsonPath('price_yearly', 90)->json('id');

        $this->putJson("/api/v1/admin/subscription-plans/{$id}", ['price_monthly' => 12, 'is_highlighted' => true])->assertOk();
        $this->assertFalse($this->plan('dealer-pro')->is_highlighted);

        $this->deleteJson('/api/v1/admin/subscription-plans/'.$this->plan('private-free')->id)->assertStatus(422);
        $this->deleteJson("/api/v1/admin/subscription-plans/{$id}")->assertOk();

        $this->putJson('/api/v1/admin/subscriptions/payment-instructions', ['instructions' => "Bank: Test Bank\nIBAN: TR00 0000"])->assertOk();
        $this->getJson('/api/v1/billing/status')->assertJsonPath('manual_instructions', "Bank: Test Bank\nIBAN: TR00 0000");
    }

    public function test_sellers_cannot_reach_admin_plan_endpoints(): void
    {
        $this->actingAs($this->seller(), 'sanctum')->getJson('/api/v1/admin/subscriptions')->assertForbidden();
    }

    public function test_reminder_is_sent_once_before_a_trial_ends(): void
    {
        $dealer = $this->seller('dealer');
        $this->actingAs($dealer, 'sanctum')->postJson('/api/v1/billing/subscribe', ['plan_id' => $this->plan('dealer-start')->id, 'cycle' => 'monthly']);

        $this->artisan('subscriptions:remind');
        Notification::assertNotSentTo($dealer, SubscriptionUpdated::class);

        $this->travel(28)->days();
        $this->artisan('subscriptions:remind');
        $this->artisan('subscriptions:remind');
        Notification::assertSentToTimes($dealer, SubscriptionUpdated::class, 1);
    }
}
