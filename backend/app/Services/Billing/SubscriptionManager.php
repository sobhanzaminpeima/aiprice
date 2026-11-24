<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\PaymentSucceeded;
use App\Notifications\SubscriptionUpdated;
use App\Support\StaffAlerts;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Subscriptions without an online payment gateway:
 *
 *  1. A seller picks a paid plan. If they have never had a trial, it starts
 *     straight away as a free trial (no card, no payment).
 *  2. Otherwise the request is recorded as "pending" and staff are alerted.
 *     The seller pays by bank transfer or cash using the instructions shown.
 *  3. Staff record the payment in the admin panel, which activates (or
 *     extends) the plan and issues a paid invoice.
 */
class SubscriptionManager
{
    public const INSTRUCTIONS_KEY = 'billing.manual_instructions';

    public function __construct(private readonly PlanEntitlements $entitlements)
    {
    }

    public static function paymentInstructions(): string
    {
        return (string) (Setting::get(self::INSTRUCTIONS_KEY) ?: 'Our team will contact you within one business day with payment details (bank transfer or cash at our office).');
    }

    public function startTrial(User $user, SubscriptionPlan $plan): Subscription
    {
        return DB::transaction(function () use ($user, $plan) {
            $this->closeOthers($user);
            $user->forceFill(['trial_used_at' => now()])->save();

            $sub = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'trial',
                'billing_cycle' => 'monthly',
                'amount' => $plan->price_monthly,
                'currency' => $plan->currency ?: 'GBP',
                'trial_ends_at' => now()->addDays($plan->trial_days),
                'current_period_start' => now(),
                'current_period_end' => now()->addDays($plan->trial_days),
            ])->load('plan');

            StaffAlerts::send('subscription', "Free trial started: {$plan->name}", null, StaffAlerts::person($user), '/admin/subscriptions',
                ['Plan' => $plan->name, 'Trial ends' => $sub->current_period_end->toFormattedDateString()]);

            return $sub;
        });
    }

    /** Move a running trial onto another plan; the trial end date stays the same. */
    public function switchTrial(Subscription $trial, SubscriptionPlan $plan): Subscription
    {
        $trial->update(['plan_id' => $plan->id, 'amount' => $plan->price_monthly, 'currency' => $plan->currency ?: 'GBP']);

        return $trial->fresh('plan');
    }

    public function requestManual(User $user, SubscriptionPlan $plan, string $cycle): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $cycle) {
            // Only the latest request matters.
            Subscription::where('user_id', $user->id)->where('status', 'pending')
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            $sub = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'billing_cycle' => $cycle,
                'amount' => $cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly,
                'currency' => $plan->currency ?: 'GBP',
                'payment_gateway' => 'manual',
            ])->load('plan');

            StaffAlerts::send('subscription', "Plan requested — awaiting payment: {$plan->name}", null, StaffAlerts::person($user), '/admin/subscriptions',
                ['Plan' => $plan->name, 'Billing' => $cycle, 'Amount' => number_format((float) $sub->amount, 2).' '.$sub->currency]);

            return $sub;
        });
    }

    /**
     * Record a payment received outside the site and activate or extend the
     * plan. The new period starts when the current paid period or trial ends,
     * so paying early never costs the seller days.
     */
    public function recordPayment(Subscription $sub, string $cycle, ?float $amount = null, ?string $reference = null, ?string $notes = null, ?int $months = null): Subscription
    {
        return DB::transaction(function () use ($sub, $cycle, $amount, $reference, $notes, $months) {
            $sub->loadMissing('plan', 'user');
            $user = $sub->user;
            $plan = $sub->plan;

            $from = now();
            $live = Subscription::where('user_id', $user->id)->whereIn('status', Subscription::LIVE)
                ->where('current_period_end', '>', now())->orderByDesc('current_period_end')->first();
            if ($live) {
                $from = $live->current_period_end->copy();
            }

            $months ??= $cycle === 'yearly' ? 12 : 1;
            $end = $from->copy()->addMonthsNoOverflow($months);
            $amount ??= (float) ($cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly);

            $this->closeOthers($user, except: $sub);
            $sub->update([
                'status' => 'active',
                'billing_cycle' => $cycle,
                'amount' => $cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly,
                'current_period_start' => $sub->status === 'active' && $sub->current_period_start ? $sub->current_period_start : now(),
                'current_period_end' => $end,
                'cancelled_at' => null,
                'reminder_sent_at' => null,
                'payment_gateway' => 'manual',
                'notes' => $notes ?? $sub->notes,
            ]);

            $payment = Payment::create([
                'user_id' => $user->id,
                'subscription_id' => $sub->id,
                'amount' => $amount,
                'currency' => $sub->currency ?: 'GBP',
                'status' => 'completed',
                'gateway' => 'manual',
                'gateway_payment_id' => $reference,
                'notes' => $notes,
            ]);

            Invoice::create([
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'invoice_number' => 'INV-'.now()->format('Y').'-'.substr($payment->payment_number, -6),
                'subtotal' => $amount,
                'tax' => 0,
                'total' => $amount,
                'currency' => $payment->currency,
                'status' => 'paid',
                'paid_at' => now(),
                'items' => [[
                    'description' => "AIPrice.app {$plan->name} ({$cycle})",
                    'period_start' => $from->toDateString(),
                    'period_end' => $end->toDateString(),
                    'amount' => $amount,
                ]],
            ]);

            $sub = $sub->fresh(['plan', 'user']);
            DB::afterCommit(fn () => $user->notify(new PaymentSucceeded($payment->fresh('invoice'), $sub)));

            return $sub;
        });
    }

    /** Staff grant a plan without a payment (partners, compensation, testing). */
    public function grant(User $user, SubscriptionPlan $plan, string $cycle, CarbonInterface $until, ?string $notes = null): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $cycle, $until, $notes) {
            $this->closeOthers($user);
            $sub = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $cycle,
                'amount' => 0,
                'currency' => $plan->currency ?: 'GBP',
                'current_period_start' => now(),
                'current_period_end' => $until,
                'payment_gateway' => 'manual',
                'notes' => $notes ?: 'Granted by staff',
            ])->load('plan', 'user');

            DB::afterCommit(fn () => $user->notify(new SubscriptionUpdated($sub, 'granted')));

            return $sub;
        });
    }

    /** A user holds at most one live or pending subscription. */
    private function closeOthers(User $user, ?Subscription $except = null): void
    {
        Subscription::where('user_id', $user->id)
            ->whereIn('status', [...Subscription::LIVE, 'pending'])
            ->when($except, fn ($q) => $q->where('id', '!=', $except->id))
            ->whereNull('gateway_subscription_id') // gateway-billed plans are ended by the gateway
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }
}
