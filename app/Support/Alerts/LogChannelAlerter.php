<?php

namespace App\Support\Alerts;

use App\Contracts\EmailFailureAlerter;
use App\Support\EmailFailureDigest;
use Illuminate\Support\Facades\Log;

/**
 * Default transport: writes the digest to a Monolog channel (config
 * 'mail-alerts.log_channel', 'slack' in prod). Repoint the channel to send it
 * elsewhere — no code change, no extra dependency.
 *
 * Logged at CRITICAL so it clears the slack channel's default level threshold
 * (env('LOG_LEVEL', 'critical')); at ERROR the alert is silently filtered out
 * whenever LOG_LEVEL is unset or set above 'error'.
 */
class LogChannelAlerter implements EmailFailureAlerter
{
    public function alert(EmailFailureDigest $digest): void
    {
        /** @var string $channel */
        $channel = config('mail-alerts.log_channel', 'slack');

        Log::channel($channel)->critical($digest->summaryLine(), $digest->context());
    }
}
