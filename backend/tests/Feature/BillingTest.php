<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\PaymentFailed;
use App\Notifications\PaymentSucceeded;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private const WHSEC = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config([
            'services.payments.gateway' => 'stripe',
            'services.stripe.secret' => 'sk_test_x',
            'services.stripe.webhook_secret' => self::WHSEC,
            'app.frontend_url' => 'https://aiprice.test',
        ]);
    }

    private function buyer(): User
    {
        $u = User::factory()->create(['role' => 'seller']);
        $u->assignRole('seller');
        return $u;
    }

    private function paidPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::where('price_monthly', '>', 0)->orderBy('price_monthly')->firstOrFail();
    }

    /** Post a webhook signed exactly the way Stripe signs it. */
    private function webhook(array $event, ?string $secret = self::WHSEC, ?int $t = null)
    {
        $body = json_encode($event);
        $t ??= time();
        $sig = hash_hmac('sha256', "{$t}.{$body}", $secret);

        return $this->call('POST', '/api/v1/webhooks/payments', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$sig}",
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $body);
    }

    private function invoicePaid(string $id, User $user, SubscriptionPlan $plan, string $subRef = 'sub_1', string $invoiceId = 'in_1'): array
    {
        return ['id' => $id, 'type' => 'invoice.paid', 'data' => ['object' => [
            'id' => $invoiceId, 'subscription' => $subRef, 'customer' => 'cus_1',
            'amount_paid' => (int) round($plan->price_monthly * 100), 'currency' => 'gbp',
            'hosted_invoice_url' => 'https://invoice.stripe.com/i/x',
            'subscription_details' => ['metadata' => ['user_id' => $user->id, 'plan_id' => $plan->id, 'cycle' => 'monthly']],
            'lines' => ['data' => [['period' => ['start' => now()->timestamp, 'end' => now()->addMonth()->timestamp]]]],
        ]]];
    }

    public function test_checkout_sends_plan_in_minor_units_and_returns_hosted_url(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/cs_1'])]);
        $user = $this->buyer();
        $plan = $this->paidPlan();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/billing/checkout', ['plan_id' => $plan->id, 'cycle' => 'monthly'])
            ->assertOk()
            ->assertJsonPath('url', 'https://checkout.stripe.com/c/cs_1');

        Http::assertSent(function (HttpRequest $r) use ($plan, $user) {
            return $r['mode'] === 'subscription'
                && $r['line_items'][0]['price_data']['unit_amount'] === (int) round($plan->price_monthly * 100)
                && $r['line_items'][0]['price_data']['recurring']['interval'] === 'month'
                && $r['metadata']['user_id'] === $user->id
                && $r['customer_email'] === $user->email
                && str_starts_with($r['success_url'], 'https://aiprice.test/billing/success');
        });
    }

    public function test_checkout_refuses_free_plans_unconfigured_gateway_and_unverified_users(): void
    {
        $user = $this->buyer();
        $free = SubscriptionPlan::where('price_monthly', 0)->firstOrFail();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/billing/checkout', ['plan_id' => $free->id, 'cycle' => 'monthly'])->assertStatus(422);

        config(['services.stripe.secret' => null]);
        $this->postJson('/api/v1/billing/checkout', ['plan_id' => $this->paidPlan()->id, 'cycle' => 'monthly'])->assertStatus(503);

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified, 'sanctum')->postJson('/api/v1/billing/checkout', ['plan_id' => $this->paidPlan()->id, 'cycle' => 'monthly'])->assertForbidden();
    }

    public function test_forged_or_stale_webhooks_are_rejected(): void
    {
        $event = $this->invoicePaid('evt_forged', $this->buyer(), $this->paidPlan());

        $this->webhook($event, secret: 'whsec_wrong')->assertStatus(400);
        $this->webhook($event, t: time() - 3600)->assertStatus(400);
        $this->postJson('/api/v1/webhooks/payments', $event)->assertStatus(400); // no signature at all

        $this->assertSame(0, Subscription::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_paid_invoice_activates_plan_records_payment_and_invoice_once(): void
    {
        Notification::fake();
        $user = $this->buyer();
        $plan = $this->paidPlan();

        // invoice.paid can arrive before checkout.session.completed.
        $this->webhook($this->invoicePaid('evt_1', $user, $plan))->assertOk();
        $this->webhook($this->invoicePaid('evt_1', $user, $plan))->assertOk(); // provider retry
        $this->webhook(['id' => 'evt_2', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'mode' => 'subscription', 'subscription' => 'sub_1', 'customer' => 'cus_1',
            'metadata' => ['user_id' => $user->id, 'plan_id' => $plan->id, 'cycle' => 'monthly'],
        ]]])->assertOk();

        $this->assertSame(1, Subscription::count());
        $sub = Subscription::first();
        $this->assertSame('active', $sub->status);
        $this->assertSame($plan->id, $sub->plan_id);
        $this->assertTrue($sub->current_period_end->isAfter(now()->addWeeks(3)));

        $this->assertSame(1, Payment::count());
        $this->assertSame('completed', Payment::first()->status);
        $this->assertSame(1, Invoice::count());
        $this->assertSame('cus_1', $user->fresh()->gateway_customer_id);
        Notification::assertSentToTimes($user, PaymentSucceeded::class, 1);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/my-subscription')->assertJsonPath('is_active', true);
        $this->getJson('/api/v1/billing/invoices')->assertJsonCount(1, 'data');
    }

    public function test_renewal_extends_period_and_adds_a_second_payment(): void
    {
        Notification::fake();
        $user = $this->buyer();
        $plan = $this->paidPlan();

        $this->webhook($this->invoicePaid('evt_a', $user, $plan, invoiceId: 'in_a'))->assertOk();
        $renewal = $this->invoicePaid('evt_b', $user, $plan, invoiceId: 'in_b');
        $renewal['data']['object']['lines']['data'][0]['period'] = ['start' => now()->addMonth()->timestamp, 'end' => now()->addMonths(2)->timestamp];
        $this->webhook($renewal)->assertOk();

        $this->assertSame(1, Subscription::count());
        $this->assertSame(2, Payment::where('status', 'completed')->count());
        $this->assertTrue(Subscription::first()->current_period_end->isAfter(now()->addMonths(2)->subDay()));
    }

    public function test_failed_payment_is_recorded_and_user_warned(): void
    {
        Notification::fake();
        $user = $this->buyer();
        $plan = $this->paidPlan();
        $this->webhook($this->invoicePaid('evt_ok', $user, $plan, invoiceId: 'in_ok'))->assertOk();

        $failed = $this->invoicePaid('evt_fail', $user, $plan, invoiceId: 'in_fail');
        $failed['type'] = 'invoice.payment_failed';
        $failed['data']['object']['amount_due'] = 2900;
        $this->webhook($failed)->assertOk();

        $this->assertSame('failed', Payment::where('gateway_payment_id', 'in_fail')->value('status'));
        Notification::assertSentTo($user, PaymentFailed::class);
    }

    public function test_refund_and_subscription_end(): void
    {
        Notification::fake();
        $user = $this->buyer();
        $plan = $this->paidPlan();
        $this->webhook($this->invoicePaid('evt_p', $user, $plan))->assertOk();

        $this->webhook(['id' => 'evt_r', 'type' => 'charge.refunded', 'data' => ['object' => ['invoice' => 'in_1', 'amount_refunded' => 2900]]])->assertOk();
        $this->assertSame('refunded', Payment::first()->status);
        $this->assertSame('void', Invoice::first()->status);

        $this->webhook(['id' => 'evt_d', 'type' => 'customer.subscription.deleted', 'data' => ['object' => ['id' => 'sub_1']]])->assertOk();
        $this->assertSame('expired', Subscription::first()->status);
    }

    public function test_user_cancel_stops_renewal_at_the_provider_first(): void
    {
        Notification::fake();
        $user = $this->buyer();
        $this->webhook($this->invoicePaid('evt_c', $user, $this->paidPlan()))->assertOk();
        $sub = Subscription::first();

        // The client retries twice, so the outage has to span all three attempts.
        Http::fake(['api.stripe.com/v1/subscriptions/sub_1' => Http::sequence()
            ->push(['error' => ['message' => 'down']], 500)
            ->push(['error' => ['message' => 'down']], 500)
            ->push(['error' => ['message' => 'down']], 500)
            ->push(['id' => 'sub_1', 'cancel_at_period_end' => true])]);

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/my-subscription/{$sub->id}/cancel")->assertStatus(502);
        $this->assertNull($sub->fresh()->cancelled_at);

        $this->postJson("/api/v1/my-subscription/{$sub->id}/cancel")->assertOk();
        $this->assertNotNull($sub->fresh()->cancelled_at);
        $this->assertSame('active', $sub->fresh()->status);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/subscriptions/sub_1') && $r['cancel_at_period_end'] === 'true');
    }

    public function test_users_only_see_their_own_invoices(): void
    {
        Notification::fake();
        $owner = $this->buyer();
        $this->webhook($this->invoicePaid('evt_i', $owner, $this->paidPlan()))->assertOk();
        $invoice = Invoice::first();

        $this->actingAs($owner, 'sanctum')->getJson("/api/v1/billing/invoices/{$invoice->id}")->assertOk();
        $this->actingAs($this->buyer(), 'sanctum')->getJson("/api/v1/billing/invoices/{$invoice->id}")->assertNotFound();
    }
}
