<?php

// Outbound e-mail failure handling. A send that exhausts its retries is recorded
// in `email_send_failures`; a scheduled command coalesces those into one alert.
// See App\Jobs\SendVoterEmail and App\Console\Commands\FlushEmailFailureAlerts.
return [

    // When false, failures are still recorded but no alert is dispatched.
    'enabled' => env('MAIL_ALERTS_ENABLED', true),

    // Alert transport: 'log' (digest -> Monolog channel) or 'mail'.
    'transport' => env('MAIL_ALERTS_TRANSPORT', 'log'),

    // For 'log': which Monolog channel carries the digest ('slack' in prod).
    'log_channel' => env('MAIL_ALERTS_LOG_CHANNEL', 'slack'),

    // For 'mail': recipient of the digest e-mail.
    'mail_to' => env('MAIL_ALERTS_MAIL_TO'),

    // Alert only once this many failures have accrued since the last alert.
    'min_failures' => (int) env('MAIL_ALERTS_MIN_FAILURES', 1),

    // Max failures one flush pulls into memory; the rest drain on later runs.
    'max_per_flush' => (int) env('MAIL_ALERTS_MAX_PER_FLUSH', 500),

    // Send-job (App\Jobs\SendVoterEmail) retry tuning.
    'retry_hours' => (int) env('MAIL_SEND_RETRY_HOURS', 6),

    // Give up after this many real send errors (rate-limit re-queues don't count).
    'max_exceptions' => (int) env('MAIL_SEND_MAX_EXCEPTIONS', 8),

    // Backoff (seconds) between send retries; last value repeats. 1m, 5m, 15m, 1h.
    'backoff' => [60, 300, 900, 3600],

];
