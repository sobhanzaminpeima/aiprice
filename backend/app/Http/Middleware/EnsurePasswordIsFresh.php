<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A user flagged force_password_change (e.g. created by an admin with a
 * password the admin knows) may only read their profile, change the
 * password, or sign out until they pick their own.
 */
class EnsurePasswordIsFresh
{
    private const ALLOWED = ['api/v1/auth/me', 'api/v1/auth/logout', 'api/v1/auth/change-password'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->force_password_change && !in_array($request->path(), self::ALLOWED, true)) {
            return response()->json([
                'message' => 'You must change your password before continuing.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }
}
