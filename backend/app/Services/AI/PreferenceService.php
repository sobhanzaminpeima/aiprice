<?php

namespace App\Services\AI;

use App\Models\Listing;
use App\Models\User;
use App\Models\UserSearchPreference;

/**
 * AI Preference Graph — persists what a user was searching for so the
 * product can say "welcome back" with something concrete, instead of
 * starting from zero every visit. Deliberately thin: one row per search,
 * no inferred/derived profile fields that aren't traceable to an action
 * the user actually took.
 */
class PreferenceService
{
    public function remember(User $user, array $intent, string $source = 'intent_composer'): UserSearchPreference
    {
        return UserSearchPreference::create([
            'user_id' => $user->id,
            'asset_type' => $intent['asset_type'],
            'budget_max' => $intent['budget_max'] ?? null,
            'city' => $intent['city'] ?? null,
            'priorities' => $intent['priorities'] ?? [],
            'source' => $source,
        ]);
    }

    public function last(User $user): ?UserSearchPreference
    {
        return UserSearchPreference::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Listings matching the given preference that were published after it
     * was saved — the concrete "N new listings match" number, not a guess.
     */
    public function newMatchesSince(UserSearchPreference $pref): int
    {
        return Listing::where('type', $pref->asset_type)
            ->where('status', 'active')
            ->when($pref->budget_max, fn ($q) => $q->where('price', '<=', $pref->budget_max))
            ->when($pref->city, fn ($q) => $q->where('city', $pref->city))
            ->where('created_at', '>', $pref->created_at)
            ->count();
    }

    public function forget(User $user, string $id): bool
    {
        return (bool) UserSearchPreference::where('user_id', $user->id)->where('id', $id)->delete();
    }
}
