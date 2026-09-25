<?php

namespace App\Services\Payments;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * What the rest of the app needs from a payment provider. Stripe is the first
 * driver; iyzico or PayTR (for a Turkish/TRNC-registered business) would
 * implement the same contract and be selected with PAYMENT_GATEWAY.
 */
interface PaymentGateway
{
    /** Short machine name stored on payments/subscriptions, e.g. "stripe". */
    public function name(): string;

    public function isConfigured(): bool;

    /** Start a hosted checkout for a recurring plan; returns the URL to redirect the user to. */
    public function createCheckout(User $user, SubscriptionPlan $plan, string $cycle): CheckoutSession;

    /** Stop renewal at the end of the paid period. */
    public function cancelAtPeriodEnd(Subscription $subscription): void;

    /**
     * Verify the webhook's signature and normalise it. Returns null for events
     * the app doesn't act on. Throws InvalidWebhook if the signature is bad.
     */
    public function parseWebhook(Request $request): ?WebhookEvent;
}
