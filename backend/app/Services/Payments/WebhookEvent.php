<?php

namespace App\Services\Payments;

use Carbon\CarbonImmutable;

/**
 * A provider-neutral view of the handful of billing events the app reacts to.
 * Drivers translate their own payloads into one of these.
 */
final class WebhookEvent
{
    public const CHECKOUT_COMPLETED = 'checkout_completed';
    public const PAYMENT_SUCCEEDED = 'payment_succeeded';
    public const PAYMENT_FAILED = 'payment_failed';
    public const PAYMENT_REFUNDED = 'payment_refunded';
    public const SUBSCRIPTION_CANCELLING = 'subscription_cancelling';
    public const SUBSCRIPTION_ENDED = 'subscription_ended';

    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly ?string $subscriptionRef = null,
        public readonly ?string $customerRef = null,
        public readonly ?string $paymentRef = null,
        public readonly ?float $amount = null,
        public readonly ?string $currency = null,
        public readonly ?CarbonImmutable $periodStart = null,
        public readonly ?CarbonImmutable $periodEnd = null,
        /** @var array<string,string> user_id, plan_id, cycle — set at checkout */
        public readonly array $metadata = [],
        public readonly ?string $receiptUrl = null,
        public readonly array $raw = [],
    ) {
    }
}
