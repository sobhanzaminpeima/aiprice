<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single source of truth for who may call which /admin/* endpoint.
 *
 * Every admin route is matched against PERMISSIONS (first match wins) and the
 * user must hold that Spatie permission. A path with no entry is refused —
 * adding an admin route without deciding who may use it fails closed.
 *
 * super_admin bypasses all checks through the Gate::before hook in
 * AppServiceProvider, so it never needs to be listed here.
 */
class EnsureAdminPermission
{
    /** @var array<string, string> path pattern (relative to api/v1/admin/) => permission */
    public const PERMISSIONS = [
        'dashboard' => 'view_dashboard',
        'me/permissions' => 'view_dashboard',

        // More specific listing actions before the listings/* catch-all.
        'listings/*/approve' => 'approve_listings',
        'listings/*/reject' => 'approve_listings',
        'listings/batch-analyze' => 'manage_ai',
        'listings' => 'manage_listings',
        'listings/*' => 'manage_listings',
        'featured-listings' => 'manage_listings',

        'users/*/approve-kyc' => 'approve_kyc',
        'users/*/reject-kyc' => 'approve_kyc',
        'kyc' => 'approve_kyc',
        'kyc/*' => 'approve_kyc',
        'users' => 'manage_users',
        'users/*' => 'manage_users',
        'agencies' => 'manage_users',
        'agencies/*' => 'manage_users',

        'inquiries' => 'manage_support',
        'inquiries/*' => 'manage_support',
        'support' => 'manage_support',
        'support/*' => 'manage_support',

        'vehicle-brands' => 'manage_vehicles',
        'vehicle-brands/*' => 'manage_vehicles',
        'vehicle-models' => 'manage_vehicles',
        'vehicle-models/*' => 'manage_vehicles',
        'property-types' => 'manage_properties',
        'property-types/*' => 'manage_properties',

        'competitor-*' => 'manage_competitors',

        'ai-insights' => 'manage_ai',
        'ai-models' => 'manage_ai',
        'ai-models/*' => 'manage_ai',
        'llm/*' => 'manage_ai',

        'social/*' => 'manage_cms',
        'homepage-sections' => 'manage_cms',
        'homepage-sections/*' => 'manage_cms',
        'content' => 'manage_cms',
        'content/*' => 'manage_cms',

        'settings' => 'manage_settings',
        'settings/*' => 'manage_settings',
        'audit-logs' => 'manage_settings',
        'audit-logs/*' => 'manage_settings',

        'analytics/*' => 'view_analytics',
        'market' => 'view_analytics',
        'market/*' => 'view_analytics',
        'live' => 'view_analytics',
        'live/*' => 'view_analytics',
        // Each export additionally checks its own data permission in ReportController.
        'reports' => 'view_analytics',
        'reports/*' => 'view_analytics',

        // Broadcasting to every user is an admin-level action.
        'announcements' => 'manage_cms',
        'announcements/*' => 'manage_cms',

        'subscription-plans' => 'manage_subscriptions',
        'subscription-plans/*' => 'manage_subscriptions',
        'subscriptions' => 'manage_subscriptions',
        'subscriptions/*' => 'manage_subscriptions',
        'financial' => 'manage_payments',
        'financial/*' => 'manage_payments',
        'payments' => 'manage_payments',
        'payments/*' => 'manage_payments',

        'advertisements' => 'manage_advertisements',
        'advertisements/*' => 'manage_advertisements',

        'fraud' => 'manage_fraud',
        'fraud/*' => 'manage_fraud',

        'notifications' => 'view_dashboard',
        'notifications/*' => 'view_dashboard',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isStaff()) {
            abort(403, 'Staff access required.');
        }

        $permission = self::permissionFor($request->path());

        if ($permission === null) {
            abort(403, 'No permission is mapped for this admin endpoint.');
        }

        if (!$user->can($permission)) {
            abort(403, "Missing permission: {$permission}.");
        }

        return $next($request);
    }

    public static function permissionFor(string $path): ?string
    {
        $relative = Str::after(trim($path, '/'), 'admin/');

        foreach (self::PERMISSIONS as $pattern => $permission) {
            if (Str::is($pattern, $relative)) {
                return $permission;
            }
        }

        return null;
    }
}
