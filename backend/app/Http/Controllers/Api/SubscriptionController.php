<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Falls back to the latest non-active subscription so a user whose plan
     * lapsed still sees what they had, instead of an empty "Free" card.
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->orderByRaw("status = 'active' DESC")
            ->latest()
            ->first();

        return response()->json([
            'subscription' => $subscription,
            'is_active' => $subscription?->status === 'active',
            'usage' => [
                'listings' => Listing::where('user_id', $user->id)->whereNull('deleted_at')->count(),
                'listing_limit' => $subscription?->plan?->max_listings,
            ],
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

        if ($subscription->status !== 'active' || $subscription->cancelled_at) {
            return response()->json(['message' => 'This subscription is not active or is already set to end.'], 422);
        }

        // Cancel at period end: it stays active (the user keeps what they paid
        // for) and the scheduled subscriptions:expire task ends it.
        $subscription->update(['cancelled_at' => now()]);

        return response()->json(['subscription' => $subscription->fresh('plan')]);
    }
}
