<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            // AWS SNS webhook: authenticated by SNS signature (sns.verify), not the
            // app token group, because AWS cannot send the Authorization/Owner headers.
            Route::prefix('api')
                ->middleware(['throttle:sns', 'sns.verify'])
                ->namespace($this->namespace)
                ->group(base_path('routes/sns.php'));

            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        // AWS SNS bounce/complaint webhook. Requests are signature-verified, so this
        // limiter only guards against forged-message floods; kept generous so a burst
        // of genuine delivery notifications during a large send isn't throttled (429
        // would otherwise trigger SNS retries/backoff and stale email-block state).
        RateLimiter::for('sns', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        });
    }
}
