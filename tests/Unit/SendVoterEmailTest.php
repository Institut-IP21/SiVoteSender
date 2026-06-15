<?php

namespace Tests\Unit;

use App\Jobs\SendVoterEmail;
use App\Models\EmailSendFailure;
use App\Support\EmailFailureDigest;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Carbon;
use Tests\Fixtures\PlainMailable;
use Tests\TestCase;

class SendVoterEmailTest extends TestCase
{
    public function testBackoffComesFromConfig(): void
    {
        config(['mail-alerts.backoff' => [5, 10, 20]]);

        $job = new SendVoterEmail('voter@example.org', new PlainMailable());

        $this->assertSame([5, 10, 20], $job->backoff());
    }

    public function testRetryUntilUsesConfiguredDeadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-14 12:00:00'));
        config(['mail-alerts.retry_hours' => 3]);

        $job = new SendVoterEmail('voter@example.org', new PlainMailable());

        $this->assertSame(
            Carbon::parse('2026-06-14 15:00:00')->getTimestamp(),
            $job->retryUntil()->getTimestamp(),
        );

        Carbon::setTestNow();
    }

    public function testMaxExceptionsComesFromConfig(): void
    {
        config(['mail-alerts.max_exceptions' => 4]);

        $job = new SendVoterEmail('voter@example.org', new PlainMailable());

        $this->assertSame(4, $job->maxExceptions);
    }

    public function testIsRateLimitedUnderTheSesLimiter(): void
    {
        $job = new SendVoterEmail('voter@example.org', new PlainMailable());

        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(RateLimited::class, $middleware[0]);
    }

    public function testDigestAggregatesFailures(): void
    {
        $failures = collect([
            new EmailSendFailure(['mailable' => 'App\\Mail\\BallotInvite', 'error' => 'Throttling: rate exceeded', 'recipient' => 'a@example.org']),
            new EmailSendFailure(['mailable' => 'App\\Mail\\BallotInvite', 'error' => 'Throttling: rate exceeded', 'recipient' => 'b@example.org']),
            new EmailSendFailure(['mailable' => 'App\\Mail\\Verification', 'error' => 'Connection timed out', 'recipient' => 'c@example.org']),
        ]);

        $digest = EmailFailureDigest::fromFailures($failures);

        $this->assertSame(3, $digest->count);
        $this->assertSame(2, $digest->byMailable['App\\Mail\\BallotInvite']);
        $this->assertSame(1, $digest->byMailable['App\\Mail\\Verification']);
        $this->assertSame(2, $digest->byError['Throttling: rate exceeded']);
        $this->assertContains('a@example.org', $digest->sampleRecipients);
        $this->assertSame('3 outbound e-mails failed to send', $digest->summaryLine());
        $this->assertStringContainsString('Throttling: rate exceeded', $digest->toText());
    }
}
