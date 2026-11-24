<?php

namespace App\Services\Billing;

use App\Models\Listing;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * What a seller's plan lets them do: how many listings they can have live at
 * once, photos per listing, featured slots and how long a listing stays up.
 *
 * A seller without a live subscription (active or free trial) is on the free
 * private-seller plan. Staff are never limited — they publish on behalf of
 * sellers and run imports.
 */
class PlanEntitlements
{
    /** Listings in these states take up a slot. Sold, expired, rejected and archived ones do not. */
    public const OCCUPYING = ['draft', 'pending', 'active'];

    /** Used only if the free plan row is missing or switched off. */
    private const FALLBACK = ['max_listings' => 2, 'max_photos_per_listing' => 10, 'max_featured' => 0, 'listing_duration_days' => 60];

    /** Hard ceiling regardless of plan, to keep storage bounded. */
    public const ABSOLUTE_MAX_PHOTOS = 30;

    public function subscription(User $user): ?Subscription
    {
        return Subscription::with('plan')
            ->where('user_id', $user->id)
            ->whereIn('status', Subscription::LIVE)
            ->where(fn ($q) => $q->whereNull('current_period_end')->orWhere('current_period_end', '>', now()))
            ->latest()
            ->first();
    }

    public function plan(User $user): ?SubscriptionPlan
    {
        return $this->subscription($user)?->plan ?? self::freePlan();
    }

    public static function freePlan(): ?SubscriptionPlan
    {
        return SubscriptionPlan::where('slug', SubscriptionPlan::FREE_SLUG)->where('is_active', true)->first();
    }

    /** @return array{plan: ?SubscriptionPlan, max_listings: int, max_photos_per_listing: int, max_featured: int, listing_duration_days: int, unlimited: bool} */
    public function limits(User $user): array
    {
        if ($user->isStaff()) {
            return ['plan' => null, 'max_listings' => PHP_INT_MAX, 'max_photos_per_listing' => self::ABSOLUTE_MAX_PHOTOS, 'max_featured' => PHP_INT_MAX, 'listing_duration_days' => 90, 'unlimited' => true];
        }

        $plan = $this->plan($user);
        $get = fn (string $k) => (int) ($plan?->{$k} ?? self::FALLBACK[$k]);

        return [
            'plan' => $plan,
            'max_listings' => $get('max_listings'),
            'max_photos_per_listing' => min(self::ABSOLUTE_MAX_PHOTOS, max(1, $get('max_photos_per_listing'))),
            'max_featured' => $get('max_featured'),
            'listing_duration_days' => max(7, $get('listing_duration_days')),
            'unlimited' => false,
        ];
    }

    public function usedListings(User $user, ?string $exceptId = null): int
    {
        return Listing::where('user_id', $user->id)
            ->whereIn('status', self::OCCUPYING)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->count();
    }

    public function usedFeatured(User $user): int
    {
        return Listing::where('user_id', $user->id)->where('status', 'active')->where('is_featured', true)->count();
    }

    /**
     * Throws a validation error when one more listing would exceed the plan.
     * $reviving is the listing being brought back (e.g. an expired one being
     * resubmitted), which does not count against itself.
     */
    public function assertCanAddListing(User $user, ?Listing $reviving = null): void
    {
        $limits = $this->limits($user);
        if ($limits['unlimited']) {
            return;
        }
        if ($this->usedListings($user, $reviving?->id) >= $limits['max_listings']) {
            throw ValidationException::withMessages([
                'plan' => "Your {$this->planName($limits)} plan allows {$limits['max_listings']} active listings. Mark a listing as sold, delete one, or upgrade your plan to add more.",
            ]);
        }
    }

    public function assertPhotoCount(User $user, int $total): void
    {
        $max = $this->limits($user)['max_photos_per_listing'];
        if ($total > $max) {
            throw ValidationException::withMessages([
                'images' => "Your plan allows {$max} photos per listing.",
            ]);
        }
    }

    public function usage(User $user): array
    {
        $limits = $this->limits($user);

        return [
            'listings' => $this->usedListings($user),
            'listing_limit' => $limits['unlimited'] ? null : $limits['max_listings'],
            'featured' => $this->usedFeatured($user),
            'featured_limit' => $limits['unlimited'] ? null : $limits['max_featured'],
            'photos_per_listing' => $limits['max_photos_per_listing'],
            'listing_duration_days' => $limits['listing_duration_days'],
            'plan_name' => $limits['plan']?->name,
        ];
    }

    /** A trial is offered once per account. */
    public function trialAvailable(User $user, SubscriptionPlan $plan): bool
    {
        return $plan->trial_days > 0
            && !$plan->isFree()
            && !$user->trial_used_at
            && !$user->isStaff()
            // Customers who have already paid us are not offered a trial again.
            && !Payment::where('user_id', $user->id)->where('status', 'completed')->exists();
    }

    private function planName(array $limits): string
    {
        return $limits['plan']?->name ?? 'free';
    }
}
