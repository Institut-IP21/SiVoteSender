<?php

namespace App\Support;

use App\Models\EmailSendFailure;
use Illuminate\Support\Collection;

/**
 * Transport-agnostic summary of a batch of failed e-mails, handed to the
 * configured {@see \App\Contracts\EmailFailureAlerter}.
 */
final class EmailFailureDigest
{
    /**
     * @param  int  $count  Total failed messages in this digest.
     * @param  array<string, int>  $byMailable  Failure count keyed by mailable class.
     * @param  array<string, int>  $byError  Failure count keyed by (truncated) error.
     * @param  list<string>  $sampleRecipients  A few example recipients.
     */
    public function __construct(
        public readonly int $count,
        public readonly array $byMailable,
        public readonly array $byError,
        public readonly array $sampleRecipients,
    ) {
    }

    /**
     * @param  Collection<int, EmailSendFailure>  $failures
     */
    public static function fromFailures(Collection $failures): self
    {
        $byMailable = $failures
            ->countBy(fn (EmailSendFailure $f): string => $f->mailable ?? 'unknown')
            ->all();

        $byError = $failures
            ->countBy(fn (EmailSendFailure $f): string => self::shorten($f->error ?? 'unknown'))
            ->all();

        $sampleRecipients = array_values($failures
            ->pluck('recipient')
            ->filter()
            ->unique()
            ->take(5)
            ->map(fn (mixed $r): string => (string) $r)
            ->all());

        return new self(
            count: $failures->count(),
            byMailable: $byMailable,
            byError: $byError,
            sampleRecipients: $sampleRecipients,
        );
    }

    public function summaryLine(): string
    {
        return sprintf(
            '%d outbound e-mail%s failed to send',
            $this->count,
            $this->count === 1 ? '' : 's',
        );
    }

    /** @return array<string, mixed> Structured detail (log/Slack context). */
    public function context(): array
    {
        return [
            'total'             => $this->count,
            'by_mailable'       => $this->byMailable,
            'by_error'          => $this->byError,
            'sample_recipients' => $this->sampleRecipients,
        ];
    }

    /** Multi-line body for the mail transport. */
    public function toText(): string
    {
        $lines = [$this->summaryLine(), ''];

        $lines[] = 'By type:';
        foreach ($this->byMailable as $mailable => $n) {
            $lines[] = sprintf('  - %s: %d', $mailable, $n);
        }

        $lines[] = '';
        $lines[] = 'By error:';
        foreach ($this->byError as $error => $n) {
            $lines[] = sprintf('  - %s: %d', $error, $n);
        }

        if ($this->sampleRecipients !== []) {
            $lines[] = '';
            $lines[] = 'Sample recipients: ' . implode(', ', $this->sampleRecipients);
        }

        return implode("\n", $lines);
    }

    private static function shorten(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return mb_strlen($value) > 140 ? mb_substr($value, 0, 137) . '...' : $value;
    }
}
