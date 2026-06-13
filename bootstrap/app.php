<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function () {
            // AWS SNS bounce/complaint webhook. Registered OUTSIDE the api group so it
            // is NOT subject to ApiAuth (AWS cannot send the app's Authorization/Owner
            // headers) — instead it is authenticated by the SNS message signature
            // (sns.verify). Prefixed with 'api' to match the api group's URL space
            // (routes/sns.php adds its own 'sns' prefix → api/sns/webhook).
            Route::prefix('api')
                ->middleware(['throttle:sns', 'sns.verify'])
                ->group(base_path('routes/sns.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The app is only reached through the reverse proxy / load balancer, so trust
        // forwarded headers to recover the real client IP. Without this, X-Forwarded-For
        // is ignored and $request->ip() returns the proxy's IP for every request —
        // collapsing per-IP rate limiters onto a single bucket.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'auth.api' => \App\Http\Middleware\ApiAuth::class,
            'sns.verify' => \App\Http\Middleware\VerifySnsMessage::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\ApiAuth::class,
        ]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/home');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->booted(function () {
        // AWS SNS bounce/complaint webhook. Requests are signature-verified, so this
        // limiter only guards against forged-message floods; kept generous so a burst
        // of genuine delivery notifications during a large send isn't throttled (429
        // would otherwise trigger SNS retries/backoff and stale email-block state).
        RateLimiter::for('sns', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        });
    })->create();
