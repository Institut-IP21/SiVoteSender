<?php

namespace App\Console\Commands;

use App\Contracts\EmailFailureAlerter;
use App\Models\EmailSendFailure;
use App\Support\EmailFailureDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Coalesces failures accumulated since the last run into one digest and
 * dispatches it via the configured alerter. Scheduled per minute.
 */
class FlushEmailFailureAlerts extends Command
{
    /** @var string */
    protected $signature = 'email:flush-failure-alerts';

    /** @var string */
    protected $description = 'Coalesce recent e-mail send failures and dispatch a single alert.';

    public function handle(EmailFailureAlerter $alerter): int
    {
        if (! config('mail-alerts.enabled', true)) {
            return self::SUCCESS;
        }

        // Bound memory: alert on a capped batch and let later runs drain the
        // rest, rather than loading a whole outage's backlog at once.
        $batchSize = max(1, (int) config('mail-alerts.max_per_flush', 500));

        /** @var \Illuminate\Support\Collection<int, EmailSendFailure> $failures */
        $failures = EmailSendFailure::query()
            ->pendingAlert()
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        $minFailures = max(1, (int) config('mail-alerts.min_failures', 1));

        if ($failures->count() < $minFailures) {
            return self::SUCCESS;
        }

        $digest = EmailFailureDigest::fromFailures($failures);

        try {
            $alerter->alert($digest);
        } catch (Throwable $e) {
            // Leave rows un-alerted so the next run retries; don't crash the scheduler.
            Log::error('Failed to dispatch e-mail-failure alert', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        EmailSendFailure::query()
            ->whereIn('id', $failures->pluck('id')->all())
            ->update(['alerted_at' => now()]);

        if ($failures->count() === $batchSize) {
            // Backlog exceeds one batch; the remainder flushes on the next run.
            Log::info('Email-failure alert batch capped; more failures pending.', ['batch' => $batchSize]);
        }

        $this->info(sprintf('Alerted on %d e-mail failure(s).', $failures->count()));

        return self::SUCCESS;
    }
}
