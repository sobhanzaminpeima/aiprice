<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Plans, subscribers, manual payments and the payment instructions sellers see. */
class SubscriptionAdminController extends Controller
{
    // ---- Plans -------------------------------------------------------------

    public function plans(): JsonResponse
    {
        return response()->json(
            SubscriptionPlan::withCount(['subscriptions as live_subscribers' => fn ($q) => $q->whereIn('status', Subscription::LIVE)])
                ->orderBy('sort_order')->orderBy('price_monthly')->get()
        );
    }

    public function storePlan(Request $request): JsonResponse
    {
        $data = $this->validatePlan($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['currency'] ??= 'GBP';

        return response()->json(SubscriptionPlan::create($data), 201);
    }

    public function updatePlan(Request $request, string $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $data = $this->validatePlan($request, $plan);

        // Making a plan "Most popular" un-highlights the others in its group.
        if (!empty($data['is_highlighted'])) {
            SubscriptionPlan::where('type', $data['type'] ?? $plan->type)->where('id', '!=', $plan->id)->update(['is_highlighted' => false]);
        }
        $plan->update($data);

        return response()->json($plan->fresh());
    }

    public function destroyPlan(string $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);
        abort_if($plan->slug === SubscriptionPlan::FREE_SLUG, 422, 'The free plan is the default for every seller. Edit its limits instead of deleting it.');
        abort_if(
            $plan->subscriptions()->whereIn('status', [...Subscription::LIVE, 'pending'])->exists(),
            422,
            'Sellers are on this plan. Switch it off (untick Active) so nobody new can choose it; existing sellers keep it until it ends.',
        );
        $plan->delete();

        return response()->json(['message' => 'Plan deleted.']);
    }

    private function validatePlan(Request $request, ?SubscriptionPlan $plan = null): array
    {
        $req = $plan ? 'sometimes' : 'required';

        $data = $request->validate([
            'name' => "$req|string|max:60",
            'description' => 'nullable|string|max:500',
            'type' => [$req, Rule::in(['seller', 'dealer', 'agency'])],
            'price_monthly' => "$req|numeric|min:0|max:100000",
            'price_yearly' => 'nullable|numeric|min:0|max:1000000',
            'currency' => 'sometimes|in:GBP,EUR,USD,TRY',
            'max_listings' => "$req|integer|min:0|max:100000",
            'max_photos_per_listing' => 'sometimes|integer|min:1|max:30',
            'max_featured' => 'sometimes|integer|min:0|max:10000',
            'listing_duration_days' => 'sometimes|integer|min:7|max:365',
            'trial_days' => 'sometimes|integer|min:0|max:365',
            'ai_price_analysis' => 'sometimes|boolean',
            'ai_trust_score' => 'sometimes|boolean',
            'ai_investment_score' => 'sometimes|boolean',
            'featured_listing' => 'sometimes|boolean',
            'priority_support' => 'sometimes|boolean',
            'is_highlighted' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0|max:1000',
            'features' => 'nullable|array|max:12',
            'features.*' => 'string|max:120',
        ]);

        if (array_key_exists('price_monthly', $data) && !isset($data['price_yearly'])) {
            $data['price_yearly'] = round((float) $data['price_monthly'] * 10, 2); // two months free
        }
        if (isset($data['max_featured'])) {
            $data['featured_listing'] = $data['max_featured'] > 0;
        }

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'plan';
        $slug = $base;
        for ($i = 2; SubscriptionPlan::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    // ---- Subscriptions -----------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $q = Subscription::with(['user:id,name,email,phone,role', 'plan:id,name,type,price_monthly,price_yearly,currency'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('name', 'like', '%'.$request->search.'%')
                ->orWhere('email', 'like', '%'.$request->search.'%')
                ->orWhere('phone', 'like', '%'.$request->search.'%')))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'trial' THEN 1 WHEN 'active' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at');

        return response()->json($q->paginate(20));
    }

    /** Give a seller a plan without payment (partners, compensation, launch deals). */
    public function grant(Request $request, SubscriptionManager $manager): JsonResponse
    {
        $v = $request->validate([
            'email' => 'required|email|exists:users,email',
            'plan_id' => 'required|uuid|exists:subscription_plans,id',
            'billing_cycle' => 'sometimes|in:monthly,yearly',
            'months' => 'sometimes|integer|min:1|max:36',
            'notes' => 'nullable|string|max:500',
        ]);
        $user = User::where('email', $v['email'])->firstOrFail();
        $plan = SubscriptionPlan::findOrFail($v['plan_id']);
        abort_if($plan->isFree(), 422, 'Everyone is on the free plan by default.');

        $sub = $manager->grant($user, $plan, $v['billing_cycle'] ?? 'monthly', now()->addMonthsNoOverflow($v['months'] ?? 1), $v['notes'] ?? null);

        return response()->json($sub->load('user:id,name,email'), 201);
    }

    /** Record a bank-transfer/cash payment: activates a request or extends a plan. */
    public function recordPayment(Request $request, string $id, SubscriptionManager $manager): JsonResponse
    {
        $sub = Subscription::with('plan', 'user')->findOrFail($id);
        abort_if(in_array($sub->status, ['cancelled', 'expired'], true) && $sub->gateway_subscription_id, 422, 'This subscription is billed online.');

        $v = $request->validate([
            'billing_cycle' => 'sometimes|in:monthly,yearly',
            'amount' => 'nullable|numeric|min:0|max:1000000',
            'months' => 'nullable|integer|min:1|max:36',
            'reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:500',
        ]);

        $sub = $manager->recordPayment(
            $sub,
            $v['billing_cycle'] ?? $sub->billing_cycle ?? 'monthly',
            isset($v['amount']) ? (float) $v['amount'] : null,
            $v['reference'] ?? null,
            $v['notes'] ?? null,
            $v['months'] ?? null,
        );

        return response()->json($sub);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $sub = Subscription::findOrFail($id);
        $v = $request->validate([
            'plan_id' => 'sometimes|uuid|exists:subscription_plans,id',
            'current_period_end' => 'sometimes|date',
            'notes' => 'nullable|string|max:500',
        ]);
        $sub->update($v);

        return response()->json($sub->fresh(['user:id,name,email', 'plan:id,name,price_monthly']));
    }

    public function cancel(string $id): JsonResponse
    {
        $sub = Subscription::findOrFail($id);
        abort_if($sub->gateway_subscription_id !== null && $sub->status === 'active', 422, 'Online-billed plans are cancelled by the seller or in the payment provider.');
        $sub->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return response()->json(['message' => 'Subscription cancelled.']);
    }

    public function stats(): JsonResponse
    {
        $paid = Subscription::where('status', 'active')->where('amount', '>', 0)->get(['amount', 'billing_cycle']);
        $mrr = $paid->sum(fn ($s) => $s->billing_cycle === 'yearly' ? (float) $s->amount / 12 : (float) $s->amount);

        return response()->json([
            'total_active' => Subscription::where('status', 'active')->count(),
            'paying' => $paid->count(),
            'trials' => Subscription::where('status', 'trial')->where('current_period_end', '>', now())->count(),
            'pending' => Subscription::where('status', 'pending')->count(),
            'mrr' => round($mrr, 2),
            'collected_this_month' => (float) Payment::where('status', 'completed')->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
            'trials_converted' => Subscription::where('status', 'active')->whereHas('user', fn ($u) => $u->whereNotNull('trial_used_at'))->where('amount', '>', 0)->count(),
            'new_this_month' => Subscription::whereIn('status', [...Subscription::LIVE])->where('created_at', '>=', now()->startOfMonth())->count(),
            'cancelled_this_month' => Subscription::whereNotNull('cancelled_at')->where('cancelled_at', '>=', now()->startOfMonth())->count(),
        ]);
    }

    // ---- Payment instructions ---------------------------------------------

    public function instructions(): JsonResponse
    {
        return response()->json(['instructions' => SubscriptionManager::paymentInstructions()]);
    }

    public function saveInstructions(Request $request): JsonResponse
    {
        $v = $request->validate(['instructions' => 'required|string|max:2000']);
        Setting::set(SubscriptionManager::INSTRUCTIONS_KEY, strip_tags($v['instructions']));

        return response()->json(['instructions' => SubscriptionManager::paymentInstructions()]);
    }
}
