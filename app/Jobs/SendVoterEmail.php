<?php

namespace App\Jobs;

use App\Models\EmailSendFailure;
use App\Models\SentMessage;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Queued, fault-tolerant delivery of a single outbound e-mail: paced under the
 * SES rate limit, retried with backoff until a deadline, and recorded on final
 * failure (see {@see \App\Console\Commands\FlushEmailFailureAlerts}).
 */
class SendVoterEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Give up after this many real send exceptions (rate-limit re-queues don't count). */
    public int $maxExceptions;

    public function __construct(
        public string $to,
        public Mailable $mailable,
        public ?int $sentMessageId = null,
    ) {
        $this->maxExceptions = (int) config('mail-alerts.max_exceptions', 8);
    }

    /**
     * Pace sends under the SES quota; over the limit the job is released back
     * (not counted as an attempt) instead of provoking throttling.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('ses')];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours((int) config('mail-alerts.retry_hours', 6));
    }

    /** @return array<int, int> Delay (seconds) between retries; last value repeats. */
    public function backoff(): array
    {
        /** @var array<int, int> $backoff */
        $backoff = config('mail-alerts.backoff', [60, 300, 900, 3600]);

        return $backoff;
    }

    public function handle(): void
    {
        Mail::to($this->to)->send($this->mailable);
    }

    /**
     * Reached only once retries are exhausted: record the permanent failure and
     * mark the originating SentMessage.
     */
    public function failed(Throwable $exception): void
    {
        $voterId = $this->sentMessageId !== null
            ? SentMessage::query()->whereKey($this->sentMessageId)->value('voter_id')
            : null;

        EmailSendFailure::create([
            'recipient'       => $this->to,
            'mailable'        => $this->mailable::class,
            'voter_id'        => $voterId,
            'sent_message_id' => $this->sentMessageId,
            'error'           => $exception->getMessage(),
            'attempts'        => $this->attempts(),
        ]);

        if ($this->sentMessageId !== null) {
            // failed_at distinguishes a failed message from one still in flight.
            SentMessage::query()
                ->whereKey($this->sentMessageId)
                ->update([
                    'failed_at'  => now(),
                    'status_msg' => mb_substr($exception->getMessage(), 0, 255),
                ]);
        }

        Log::error('Outbound e-mail permanently failed', [
            'to'       => $this->to,
            'mailable' => $this->mailable::class,
            'error'    => $exception->getMessage(),
        ]);
    }
}
