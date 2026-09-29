<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Must run in register(), not boot(): Scramble's own service provider
        // reads this flag during ITS boot() to decide whether to register its
        // default /docs/api routes, and Laravel runs every provider's
        // register() before any provider's boot(). Setting it here — rather
        // than in routes/web.php, which loads after all providers boot — is
        // what actually suppresses /docs/api before it's baked in, so only
        // our /docs route (registered in routes/web.php) exists.
        Scramble::ignoreDefaultRoutes();
    }

    public function boot(): void
    {
        // Behind GoDaddy's proxy/SSL, force generated URLs (assets, Filament) to https.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // OTP endpoints: tight limit keyed by phone number to blunt SMS abuse.
        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(5)->by($request->input('phone') ?: $request->ip()),
            Limit::perDay(50)->by($request->input('phone') ?: $request->ip()),
        ]);

        // Delivery-partner password login: blunts brute-forcing a partner's password.
        RateLimiter::for('partner-login', fn (Request $request) => [
            Limit::perMinute(10)->by($request->input('phone_number') ?: $request->ip()),
            Limit::perDay(100)->by($request->input('phone_number') ?: $request->ip()),
        ]);
    }
}
