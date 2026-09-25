<?php

namespace App\Services\Payments;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe Checkout (hosted page) for recurring plans. Talks to the REST API
 * directly so there is no SDK to keep in sync. Card data never touches our
 * servers — the user pays on Stripe's page and we learn the outcome from
 * signed webhooks.
 */
class StripeGateway implements PaymentGateway
{
    private const API = 'https://api.stripe.com/v1';
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function __construct(
        private readonly ?string $secret,
        private readonly ?string $webhookSecret,
        private readonly string $frontendUrl,
    ) {
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function isConfigured(): bool
    {
        return !empty($this->secret) && !empty($this->webhookSecret);
    }

    public function createCheckout(User $user, SubscriptionPlan $plan, string $cycle): CheckoutSession
    {
        $price = $cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
        $metadata = ['user_id' => $user->id, 'plan_id' => $plan->id, 'cycle' => $cycle];

        $params = [
            'mode' => 'subscription',
            'client_reference_id' => $user->id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($plan->currency ?: 'GBP'),
                    // Stripe takes minor units (pence); round to avoid float drift like 28.999999.
                    'unit_amount' => (int) round($price * 100),
                    'recurring' => ['interval' => $cycle === 'yearly' ? 'year' : 'month'],
                    'product_data' => ['name' => "AIPrice.app {$plan->name}"],
                ],
            ]],
            'metadata' => $metadata,
            // Copied onto the subscription so renewal invoices carry it too.
            'subscription_data' => ['metadata' => $metadata],
            'allow_promotion_codes' => 'true',
            'success_url' => rtrim($this->frontendUrl, '/').'/billing/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => rtrim($this->frontendUrl, '/').'/pricing?checkout=cancelled',
        ];

        // Reuse the Stripe customer so their cards and invoices stay in one place.
        if ($user->gateway_customer_id) {
            $params['customer'] = $user->gateway_customer_id;
        } else {
            $params['customer_email'] = $user->email;
        }

        $response = $this->http()->asForm()->post(self::API.'/checkout/sessions', $params);

        if ($response->failed()) {
            throw new RuntimeException('Stripe checkout failed: '.($response->json('error.message') ?? $response->status()));
        }

        return new CheckoutSession($response->json('id'), $response->json('url'));
    }

    public function cancelAtPeriodEnd(Subscription $subscription): void
    {
        if (!$subscription->gateway_subscription_id) {
            return;
        }

        $response = $this->http()->asForm()->post(
            self::API.'/subscriptions/'.$subscription->gateway_subscription_id,
            ['cancel_at_period_end' => 'true'],
        );

        if ($response->failed()) {
            throw new RuntimeException('Stripe cancel failed: '.($response->json('error.message') ?? $response->status()));
        }
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $payload = $request->getContent();
        $this->verifySignature($payload, (string) $request->header('Stripe-Signature'));

        $event = json_decode($payload, true);
        $object = $event['data']['object'] ?? [];
        $id = $event['id'] ?? throw new InvalidWebhook('Missing event id.');

        return match ($event['type'] ?? null) {
            'checkout.session.completed' => ($object['mode'] ?? null) === 'subscription'
                ? new WebhookEvent(
                    id: $id,
                    type: WebhookEvent::CHECKOUT_COMPLETED,
                    subscriptionRef: $object['subscription'] ?? null,
                    customerRef: $object['customer'] ?? null,
                    metadata: $object['metadata'] ?? [],
                    raw: $event,
                )
                : null,

            'invoice.paid' => new WebhookEvent(
                id: $id,
                type: WebhookEvent::PAYMENT_SUCCEEDED,
                subscriptionRef: $this->invoiceSubscription($object),
                customerRef: $object['customer'] ?? null,
                paymentRef: $object['id'] ?? null,
                amount: isset($object['amount_paid']) ? $object['amount_paid'] / 100 : null,
                currency: strtoupper($object['currency'] ?? 'GBP'),
                periodStart: $this->ts($object['lines']['data'][0]['period']['start'] ?? null),
                periodEnd: $this->ts($object['lines']['data'][0]['period']['end'] ?? null),
                metadata: $this->invoiceMetadata($object),
                receiptUrl: $object['hosted_invoice_url'] ?? null,
                raw: $event,
            ),

            'invoice.payment_failed' => new WebhookEvent(
                id: $id,
                type: WebhookEvent::PAYMENT_FAILED,
                subscriptionRef: $this->invoiceSubscription($object),
                customerRef: $object['customer'] ?? null,
                paymentRef: $object['id'] ?? null,
                amount: isset($object['amount_due']) ? $object['amount_due'] / 100 : null,
                currency: strtoupper($object['currency'] ?? 'GBP'),
                metadata: $this->invoiceMetadata($object),
                raw: $event,
            ),

            'charge.refunded' => isset($object['invoice']) ? new WebhookEvent(
                id: $id,
                type: WebhookEvent::PAYMENT_REFUNDED,
                paymentRef: $object['invoice'],
                amount: isset($object['amount_refunded']) ? $object['amount_refunded'] / 100 : null,
                raw: $event,
            ) : null,

            'customer.subscription.updated' => ($object['cancel_at_period_end'] ?? false)
                ? new WebhookEvent(id: $id, type: WebhookEvent::SUBSCRIPTION_CANCELLING, subscriptionRef: $object['id'] ?? null, raw: $event)
                : null,

            'customer.subscription.deleted' => new WebhookEvent(
                id: $id, type: WebhookEvent::SUBSCRIPTION_ENDED, subscriptionRef: $object['id'] ?? null, raw: $event,
            ),

            default => null,
        };
    }

    /**
     * Stripe-Signature: t=<unix>,v1=<hex hmac>[,v1=…]. The signed payload is
     * "<t>.<raw body>" with HMAC-SHA256 and the endpoint's signing secret.
     */
    private function verifySignature(string $payload, string $header): void
    {
        if (!$this->webhookSecret) {
            throw new InvalidWebhook('Webhook secret not configured.');
        }

        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$k, $v] = array_pad(explode('=', trim($pair), 2), 2, null);
            if ($k !== null && $v !== null) {
                $parts[$k][] = $v;
            }
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);
        $signatures = $parts['v1'] ?? [];

        if (!$timestamp || !$signatures) {
            throw new InvalidWebhook('Malformed signature header.');
        }
        if (abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            throw new InvalidWebhook('Signature timestamp outside tolerance.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $this->webhookSecret);
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return;
            }
        }

        throw new InvalidWebhook('Signature mismatch.');
    }

    private function invoiceSubscription(array $invoice): ?string
    {
        // Newer API versions nest it under parent.subscription_details.
        return $invoice['subscription']
            ?? $invoice['parent']['subscription_details']['subscription']
            ?? null;
    }

    private function invoiceMetadata(array $invoice): array
    {
        return $invoice['subscription_details']['metadata']
            ?? $invoice['parent']['subscription_details']['metadata']
            ?? $invoice['lines']['data'][0]['metadata']
            ?? [];
    }

    private function ts($value): ?CarbonImmutable
    {
        return $value ? CarbonImmutable::createFromTimestamp((int) $value) : null;
    }

    private function http(): PendingRequest
    {
        if (!$this->secret) {
            throw new RuntimeException('Stripe is not configured.');
        }

        return Http::withToken($this->secret)->timeout(20)->retry(2, 500, throw: false);
    }
}
