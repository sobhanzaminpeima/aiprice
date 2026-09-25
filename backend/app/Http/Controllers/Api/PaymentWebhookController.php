<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\BillingService;
use App\Services\Payments\InvalidWebhook;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public endpoint the payment provider calls. Authenticity comes from the
 * signature check in the gateway driver, not from a session or token.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, BillingService $billing): JsonResponse
    {
        try {
            $event = $gateway->parseWebhook($request);
        } catch (InvalidWebhook $e) {
            Log::warning('Rejected payment webhook', ['reason' => $e->getMessage(), 'ip' => $request->ip()]);
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        if ($event) {
            // Let exceptions surface as 500 so the provider retries later;
            // the idempotency table makes a retry safe.
            $billing->handle($event, $gateway->name());
        }

        return response()->json(['received' => true]);
    }
}
