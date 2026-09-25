<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // The API has no web reset page — send users to the SPA's reset form.
        ResetPassword::createUrlUsing(fn ($user, string $token) =>
            rtrim(config('app.frontend_url'), '/').'/reset-password?token='.$token.'&email='.urlencode($user->email)
        );

        // super_admin holds every permission, including ones added later.
        Gate::before(fn ($user) => method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin() ? true : null);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // Keyed on email+IP so one attacker can't lock a victim out from
        // elsewhere, plus a looser per-IP cap against credential stuffing.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(3)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perHour(20)->by($request->ip()),
        ]);
    }
}
