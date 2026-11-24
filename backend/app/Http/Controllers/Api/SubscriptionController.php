<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\Billing\PlanEntitlements;
use App\Services\Billing\SubscriptionManager;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * The live plan (paid or trial), any request awaiting payment, and usage
     * against the plan's limits. Falls back to the latest ended subscription
     * so a user whose plan lapsed still sees what they had.
     */
    public function mine(Request $request, PlanEntitlements $entitlements): JsonResponse
    {
        $user = $request->user();
        $live = $entitlements->subscription($user);

        $subscription = $live ?? Subscription::with('plan')
            ->where('user_id', $user->id)
            ->whereIn('status', ['expired', 'cancelled'])
            ->latest()
            ->first();

        $pending = Subscription::with('plan')->where('user_id', $user->id)->where('status', 'pending')->latest()->first();
        $free = PlanEntitlements::freePlan();

        return response()->json([
            'subscription' => $subscription,
            'is_active' => (bool) $live,
            'is_trial' => $live?->status === 'trial',
            'pending' => $pending,
            'payment_instructions' => $pending ? SubscriptionManager::paymentInstructions() : null,
            'trial_eligible' => !$user->trial_used_at && !\App\Models\Payment::where('user_id', $user->id)->where('status', 'completed')->exists(),
            'free_plan' => $free?->only(['id', 'name', 'max_listings', 'max_photos_per_listing']),
            'usage' => $entitlements->usage($user),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        return response()->json(
            Subscription::with('plan')
                ->where('user_id', $request->user()->id)
                ->orderByDesc('created_at')
                ->paginate(20)
        );
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $subscription = Subscription::where('user_id', $request->user()->id)->findOrFail($id);

        // Withdrawing a request that was never paid simply closes it.
        if ($subscription->status === 'pending') {
            $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            return response()->json(['subscription' => $subscription->fresh('plan')]);
        }

        if (!in_array($subscription->status, Subscription::LIVE, true) || $subscription->cancelled_at) {
            return response()->json(['message' => 'This subscription is not active or is already set to end.'], 422);
        }

        // Stop the provider from charging again before recording it locally —
        // if that call fails, the user must not be told they're cancelled.
        if ($subscription->gateway_subscription_id) {
            try {
                app(PaymentGateway::class)->cancelAtPeriodEnd($subscription);
            } catch (\Throwable $e) {
                report($e);
                return response()->json(['message' => 'We could not reach the payment provider. Please try again in a moment.'], 502);
            }
        }

        // Cancel at period end: it stays active (the user keeps what they paid
        // for) and the scheduled subscriptions:expire task ends it.
        $subscription->update(['cancelled_at' => now()]);

        return response()->json(['subscription' => $subscription->fresh('plan')]);
    }
}
