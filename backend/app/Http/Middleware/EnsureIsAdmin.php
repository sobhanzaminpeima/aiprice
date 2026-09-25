<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every /admin/* route only requires auth:sanctum today — any logged-in
 * user (a buyer, a seller) can call it. This closes that gap: the whole
 * admin API group must also require an admin/super_admin role.
 */
class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || !method_exists($user, 'isAdmin') || !$user->isAdmin()) {
            abort(403, 'Admin access required.');
        }
        return $next($request);
    }
}
