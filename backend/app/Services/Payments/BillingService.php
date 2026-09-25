<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\PaymentFailed;
use App\Notifications\PaymentSucceeded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies normalised webhook events to local state. Provider-agnostic: it only
 * sees WebhookEvent, so a second gateway reuses all of this.
 *
 * Gateways deliver events at least once and in no guaranteed order (the first
 * invoice.paid can beat checkout.session.completed), so every handler is
 * written to be safe to run twice and in either order.
 */
class BillingService
{
    public function handle(WebhookEvent $event, string $gateway): void
    {
        DB::transaction(function () use ($event, $gateway) {
            // Idempotency: an event id is processed at most once.
            $inserted = DB::table('webhook_events')->insertOrIgnore([
                'id' => $gateway.':'.$event->id,
                'gateway' => $gateway,
                'type' => $event->type,
                'processed_at' => now(),
            ]);
            if ($inserted === 0) {
                return;
            }

            match ($event->type) {
                WebhookEvent::CHECKOUT_COMPLETED => $this->checkoutCompleted($event, $gateway),
                WebhookEvent::PAYMENT_SUCCEEDED => $this->paymentSucceeded($event, $gateway),
                WebhookEvent::PAYMENT_FAILED => $this->paymentFailed($event, $gateway),
                WebhookEvent::PAYMENT_REFUNDED => $this->paymentRefunded($event, $gateway),
                WebhookEvent::SUBSCRIPTION_CANCELLING => $this->subscriptionCancelling($event),
                WebhookEvent::SUBSCRIPTION_ENDED => $this->subscriptionEnded($event),
            };
        });
    }

    private function checkoutCompleted(WebhookEvent $e, string $gateway): void
    {
        $subscription = $this->resolveSubscription($e, $gateway);
        if (!$subscription) {
            return;
        }

        if ($e->customerRef) {
            // Not mass-assignable on purpose: only billing may set it.
            $subscription->user->forceFill(['gateway_customer_id' => $e->customerRef])->save();
        }
    }

    private function paymentSucceeded(WebhookEvent $e, string $gateway): void
    {
        $subscription = $this->resolveSubscription($e, $gateway);
        if (!$subscription) {
            return;
        }

        $subscription->update(array_filter([
            'status' => 'active',
            'current_period_start' => $e->periodStart,
            'current_period_end' => $e->periodEnd,
        ]));

        $payment = Payment::firstOrCreate(
            ['gateway' => $gateway, 'gateway_payment_id' => $e->paymentRef],
            [
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'amount' => $e->amount ?? $subscription->amount,
                'currency' => $e->currency ?? $subscription->currency,
                'status' => 'completed',
                'gateway_receipt' => $e->receiptUrl,
            ],
        );
        // A failed attempt on the same invoice may have been recorded first.
        if ($payment->status !== 'completed') {
            $payment->update(['status' => 'completed', 'gateway_receipt' => $e->receiptUrl]);
        }

        if (!$payment->invoice()->exists()) {
            $amount = (float) $payment->amount;
            Invoice::create([
                'user_id' => $payment->user_id,
                'payment_id' => $payment->id,
                'invoice_number' => 'INV-'.now()->format('Y').'-'.substr($payment->payment_number, -6),
                'subtotal' => $amount,
                'tax' => 0,
                'total' => $amount,
                'currency' => $payment->currency,
                'status' => 'paid',
                'paid_at' => now(),
                'items' => [[
                    'description' => "AIPrice.app {$subscription->plan?->name} ({$subscription->billing_cycle})",
                    'period_start' => $e->periodStart?->toDateString(),
                    'period_end' => $e->periodEnd?->toDateString(),
                    'amount' => $amount,
                ]],
            ]);

            $subscription->user->notify(new PaymentSucceeded($payment->fresh('invoice'), $subscription));
        }
    }

    private function paymentFailed(WebhookEvent $e, string $gateway): void
    {
        $subscription = $this->findSubscription($e, $gateway);
        $userId = $subscription?->user_id ?? ($e->metadata['user_id'] ?? null);
        if (!$userId) {
            Log::warning('Payment failure for unknown subscription', ['ref' => $e->subscriptionRef]);
            return;
        }

        $payment = Payment::firstOrCreate(
            ['gateway' => $gateway, 'gateway_payment_id' => $e->paymentRef],
            [
                'user_id' => $userId,
                'subscription_id' => $subscription?->id,
                'amount' => $e->amount ?? 0,
                'currency' => $e->currency ?? 'GBP',
                'status' => 'failed',
            ],
        );

        if ($payment->wasRecentlyCreated) {
            User::find($userId)?->notify(new PaymentFailed($payment, $subscription));
        }
    }

    private function paymentRefunded(WebhookEvent $e, string $gateway): void
    {
        Payment::where('gateway', $gateway)->where('gateway_payment_id', $e->paymentRef)
            ->update(['status' => 'refunded']);
        Invoice::whereHas('payment', fn ($q) => $q->where('gateway', $gateway)->where('gateway_payment_id', $e->paymentRef))
            ->update(['status' => 'void']);
    }

    private function subscriptionCancelling(WebhookEvent $e): void
    {
        Subscription::where('gateway_subscription_id', $e->subscriptionRef)
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => now()]);
    }

    private function subscriptionEnded(WebhookEvent $e): void
    {
        Subscription::where('gateway_subscription_id', $e->subscriptionRef)
            ->update(['status' => 'expired']);
    }

    private function findSubscription(WebhookEvent $e, string $gateway): ?Subscription
    {
        return $e->subscriptionRef
            ? Subscription::with('user', 'plan')->where('payment_gateway', $gateway)->where('gateway_subscription_id', $e->subscriptionRef)->first()
            : null;
    }

    /**
     * Find the local subscription for this event, or create it from the
     * checkout metadata if this is the first event we've seen for it.
     */
    private function resolveSubscription(WebhookEvent $e, string $gateway): ?Subscription
    {
        if ($existing = $this->findSubscription($e, $gateway)) {
            return $existing;
        }

        $user = User::find($e->metadata['user_id'] ?? null);
        $plan = SubscriptionPlan::find($e->metadata['plan_id'] ?? null);
        $cycle = ($e->metadata['cycle'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';

        if (!$user || !$plan || !$e->subscriptionRef) {
            Log::warning('Billing event without usable metadata', ['type' => $e->type, 'ref' => $e->subscriptionRef, 'metadata' => $e->metadata]);
            return null;
        }

        // Switching plans: the previous gateway subscription stops renewing and
        // its local record is closed, so the user never holds two active plans.
        Subscription::where('user_id', $user->id)->where('status', 'active')->get()->each(function (Subscription $old) {
            try {
                app(PaymentGateway::class)->cancelAtPeriodEnd($old);
            } catch (\Throwable $ex) {
                Log::error('Could not cancel superseded subscription', ['id' => $old->id, 'error' => $ex->getMessage()]);
            }
            $old->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        });

        return Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => $cycle,
            'amount' => $cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly,
            'currency' => $plan->currency ?: 'GBP',
            'current_period_start' => $e->periodStart ?? now(),
            'current_period_end' => $e->periodEnd ?? ($cycle === 'yearly' ? now()->addYear() : now()->addMonth()),
            'payment_gateway' => $gateway,
            'gateway_subscription_id' => $e->subscriptionRef,
        ])->load('user', 'plan');
    }
}
