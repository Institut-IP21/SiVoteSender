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
            // Outside the api group on purpose: AWS cannot send ApiAuth headers, the SNS signature is the auth.
            Route::prefix('api')
                ->middleware(['throttle:sns', 'sns.verify'])
                ->group(base_path('routes/sns.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Never '*': it lets any caller spoof X-Forwarded-For/-Host past the per-IP limiter.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

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
        // Generous on purpose: a 429 on a genuine bounce burst makes SNS back off and leaves block state stale.
        RateLimiter::for('sns', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        });

        RateLimiter::for('ses', function () {
            $perSecond = (int) config('services.ses.rate_per_second', 14);

            return Limit::perSecond(max(1, $perSecond));
        });
    })->create();
