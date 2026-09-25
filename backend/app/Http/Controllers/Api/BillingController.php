<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The gateway is method-injected, not constructor-injected: Laravel caches
 * controller instances on the route, so a constructor-held gateway would keep
 * whatever config it was built with for the life of the worker.
 */
class BillingController extends Controller
{
    /** Lets the pricing page know whether to show "Subscribe" or "Contact us". */
    public function status(PaymentGateway $gateway): JsonResponse
    {
        return response()->json(['gateway' => $gateway->name(), 'enabled' => $gateway->isConfigured()]);
    }

    public function checkout(Request $request, PaymentGateway $gateway): JsonResponse
    {
        $v = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'cycle' => 'required|in:monthly,yearly',
        ]);

        $plan = SubscriptionPlan::where('is_active', true)->findOrFail($v['plan_id']);
        $price = $v['cycle'] === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
        abort_if($price <= 0, 422, 'This plan is free — no payment needed.');

        abort_unless($gateway->isConfigured(), 503, 'Online payments are not available yet. Please contact us to subscribe.');

        $user = $request->user();
        $current = $user->activeSubscription()->first();
        abort_if(
            $current && $current->plan_id === $plan->id && $current->billing_cycle === $v['cycle'] && !$current->cancelled_at,
            422,
            'You are already on this plan.',
        );

        try {
            $session = $gateway->createCheckout($user, $plan, $v['cycle']);
        } catch (\Throwable $e) {
            Log::error('Checkout creation failed', ['user' => $user->id, 'plan' => $plan->id, 'error' => $e->getMessage()]);
            abort(502, 'The payment provider is not responding. Please try again in a moment.');
        }

        return response()->json(['url' => $session->url, 'session_id' => $session->id]);
    }

    public function invoices(Request $request): JsonResponse
    {
        return response()->json(
            Invoice::where('user_id', $request->user()->id)->orderByDesc('created_at')->paginate(20)
        );
    }

    public function invoice(Request $request, string $id): JsonResponse
    {
        $invoice = Invoice::with(['payment:id,payment_number,gateway,gateway_receipt', 'user:id,name,email', 'user.profile:user_id,business_name,address,city,country,tax_id'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($invoice);
    }
}
