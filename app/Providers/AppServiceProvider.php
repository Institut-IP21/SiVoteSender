<?php

namespace App\Providers;

use App\Contracts\EmailFailureAlerter;
use App\Support\Alerts\LogChannelAlerter;
use App\Support\Alerts\MailAlerter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    #[\Override]
    public function register(): void
    {
        // Pick the e-mail-failure alert transport from config.
        // The RateLimiter's backing store is configured natively via
        // config('cache.limiter') (env RATE_LIMITER_STORE); see config/cache.php.
        $this->app->bind(EmailFailureAlerter::class, function (): EmailFailureAlerter {
            return match (config('mail-alerts.transport', 'log')) {
                'mail'  => new MailAlerter(),
                default => new LogChannelAlerter(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
