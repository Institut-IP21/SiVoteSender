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

        // Throttle before ApiAuth so token guessing is limited.
        $middleware->api(prepend: [
            'throttle:api',
            \App\Http\Middleware\ApiAuth::class,
        ]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/home');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // A QueryException message is the whole SQL statement (megabytes for a bulk insert):
        // report it truncated, never log/return it in full.
        $exceptions->dontReport(\Illuminate\Database\QueryException::class);
        $exceptions->reportable(function (\Illuminate\Database\QueryException $e) {
            \Illuminate\Support\Facades\Log::error(mb_substr($e->getMessage(), 0, 1000), ['exception' => $e::class]);
        })->stop();

        // API 500s are generic whatever APP_DEBUG says: no SQL, paths or messages on the wire.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                || $e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Illuminate\Auth\AuthenticationException
                || $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                || $e instanceof \Illuminate\Http\Exceptions\HttpResponseException
                || $e instanceof \Illuminate\Session\TokenMismatchException) {
                return null;
            }

            return response()->json(['error' => 'server_error'], 500);
        });
    })->booted(function () {
        // Generous on purpose: a 429 on a genuine bounce burst makes SNS back off and leaves block state stale.
        RateLimiter::for('sns', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        });

        // The API had no limiter, so the token could be guessed at full speed.
        // Failed logins are counted per client network (IPv4 address, IPv6 /64) at
        // 120/min; once that bucket is full EVERY request from there gets 429, a
        // correct token included. Valid traffic (web_app's own batches) isn't capped.
        RateLimiter::for('api', function (Request $request) {
            $ip = (string) ($request->ip() ?? 'unknown');
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                $ip = substr(bin2hex((string) inet_pton($ip)), 0, 16) . '::/64';
            }
            $key = 'api-authfail|' . $ip;

            if (!\App\Http\Middleware\ApiAuth::isKnownToken($request->header('Authorization'))) {
                // Certain to 401: count it before handling, so concurrent guesses can't slip past.
                return Limit::perMinute(120)->by($key);
            }

            // Checked, and counted only on a 401 (a valid token without an Owner).
            return Limit::perMinute(120)->by($key)
                ->after(fn ($response): bool => $response->getStatusCode() === 401);
        });

        RateLimiter::for('ses', function () {
            $perSecond = (int) config('services.ses.rate_per_second', 14);

            return Limit::perSecond(max(1, $perSecond));
        });
    })->create();
