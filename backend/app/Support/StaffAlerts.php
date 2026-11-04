<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\StaffAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Sends a StaffAlert to everyone who handles user requests. */
class StaffAlerts
{
    /** Active staff with manage_support, plus every active admin and super admin. */
    public static function recipients(): Collection
    {
        $byPermission = User::permission('manage_support')->where('is_active', true)->get();
        $admins = User::role(['super_admin', 'admin'])->where('is_active', true)->get();

        return $byPermission->merge($admins)->unique('id')->values();
    }

    public static function send(string $category, string $title, ?string $body = null, array $from = [], ?string $link = null, array $details = []): void
    {
        try {
            $to = self::recipients();
            if ($to->isNotEmpty()) {
                Notification::send($to, new StaffAlert($category, $title, $body, $from, $link, $details));
            }
        } catch (Throwable $e) {
            // An alert must never break the user's request.
            Log::warning('Staff alert failed: '.$e->getMessage(), ['category' => $category]);
        }
    }

    public static function person(?User $u): array
    {
        return $u ? ['name' => $u->name, 'email' => $u->email, 'phone' => $u->phone] : [];
    }
}
