<?php

namespace Tests\Feature;

use App\Contracts\EmailFailureAlerter;
use App\Jobs\SendVoterEmail;
use App\Models\EmailSendFailure;
use App\Models\SentMessage;
use App\Models\Voter;
use App\Models\VoterList;
use App\Support\Alerts\LogChannelAlerter;
use App\Support\Alerts\MailAlerter;
use App\Support\EmailFailureDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\Fixtures\PlainMailable;
use Tests\TestCase;

class EmailFailureAlertingTest extends TestCase
{
    use RefreshDatabase;

    private string $owner = 'a2c88f6a-f437-4080-80f0-fe40f596c050';

    /**
     * A capturing alerter so tests can assert what (if anything) was dispatched.
     */
    private function spyAlerter(): EmailFailureAlerter
    {
        return new class implements EmailFailureAlerter {
            /** @var list<EmailFailureDigest> */
            public array $digests = [];

            public function alert(EmailFailureDigest $digest): void
            {
                $this->digests[] = $digest;
            }
        };
    }

    public function testFailedJobRecordsFailureAndLeavesBreadcrumb(): void
    {
        $voterlist = VoterList::factory()
            ->has(Voter::factory())
            ->create(['owner' => $this->owner]);
        $voter = $voterlist->voters->first();

        $sentMessage = SentMessage::factory()->create([
            'voter_id'     => $voter->id,
            'voterlist_id' => $voterlist->id,
            'status'       => SentMessage::STATUS_SENT,
        ]);

        $job = new SendVoterEmail($voter->email, new PlainMailable(), $sentMessage->id);
        $job->failed(new RuntimeException('Throttling: Maximum sending rate exceeded'));

        $this->assertDatabaseHas('email_send_failures', [
            'recipient'       => $voter->email,
            'mailable'        => PlainMailable::class,
            'sent_message_id' => $sentMessage->id,
            'voter_id'        => $voter->id,
        ]);

        $failure = EmailSendFailure::query()->firstOrFail();
        $this->assertStringContainsString('Throttling', (string) $failure->error);
        $this->assertNull($failure->alerted_at);

        $fresh = $sentMessage->fresh();
        $this->assertStringContainsString('Throttling', (string) $fresh?->status_msg);
        // The message is now distinguishable from one still in flight.
        $this->assertNotNull($fresh?->failed_at);
    }

    public function testFlushCapsTheBatchAndDrainsTheRemainderNextRun(): void
    {
        config(['mail-alerts.max_per_flush' => 2]);

        $spy = $this->spyAlerter();
        $this->app->instance(EmailFailureAlerter::class, $spy);

        EmailSendFailure::query()->insert([
            ['recipient' => 'a@example.org', 'error' => 'Throttling', 'attempts' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['recipient' => 'b@example.org', 'error' => 'Throttling', 'attempts' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['recipient' => 'c@example.org', 'error' => 'Throttling', 'attempts' => 8, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('email:flush-failure-alerts')->assertSuccessful();
        $this->assertSame(2, $spy->digests[0]->count);
        $this->assertSame(1, EmailSendFailure::query()->pendingAlert()->count());

        $this->artisan('email:flush-failure-alerts')->assertSuccessful();
        $this->assertSame(1, $spy->digests[1]->count);
        $this->assertSame(0, EmailSendFailure::query()->pendingAlert()->count());
    }

    public function testFlushCoalescesFailuresAndMarksThemAlerted(): void
    {
        $spy = $this->spyAlerter();
        $this->app->instance(EmailFailureAlerter::class, $spy);

        EmailSendFailure::query()->insert([
            ['recipient' => 'a@example.org', 'mailable' => TestMail::class, 'error' => 'Throttling', 'attempts' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['recipient' => 'b@example.org', 'mailable' => TestMail::class, 'error' => 'Throttling', 'attempts' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['recipient' => 'c@example.org', 'mailable' => TestMail::class, 'error' => 'Timeout', 'attempts' => 8, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('email:flush-failure-alerts')->assertSuccessful();

        $this->assertCount(1, $spy->digests);
        $this->assertSame(3, $spy->digests[0]->count);
        $this->assertSame(0, EmailSendFailure::query()->pendingAlert()->count());

        // A second run has nothing pending — no further alert.
        $this->artisan('email:flush-failure-alerts')->assertSuccessful();
        $this->assertCount(1, $spy->digests);
    }

    public function testThresholdHoldsBackSmallBatches(): void
    {
        config(['mail-alerts.min_failures' => 5]);

        $spy = $this->spyAlerter();
        $this->app->instance(EmailFailureAlerter::class, $spy);

        EmailSendFailure::query()->insert([
            ['recipient' => 'a@example.org', 'error' => 'Throttling', 'attempts' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['recipient' => 'b@example.org', 'error' => 'Throttling', 'attempts' => 8, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('email:flush-failure-alerts')->assertSuccessful();

        $this->assertCount(0, $spy->digests);
        // Records are held, never dropped — they roll into the next window.
        $this->assertSame(2, EmailSendFailure::query()->pendingAlert()->count());
    }

    public function testDisabledSwitchSuppressesAlerts(): void
    {
        config(['mail-alerts.enabled' => false]);

        $spy = $this->spyAlerter();
        $this->app->instance(EmailFailureAlerter::class, $spy);

        EmailSendFailure::query()->insert([
            ['recipient' => 'a@example.org', 'error' => 'Throttling', 'attempts' => 8, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('email:flush-failure-alerts')->assertSuccessful();

        $this->assertCount(0, $spy->digests);
        $this->assertSame(1, EmailSendFailure::query()->pendingAlert()->count());
    }

    public function testTransportSelectionResolvesConfiguredImplementation(): void
    {
        config(['mail-alerts.transport' => 'mail']);
        $this->assertInstanceOf(MailAlerter::class, $this->app->make(EmailFailureAlerter::class));

        config(['mail-alerts.transport' => 'log']);
        $this->assertInstanceOf(LogChannelAlerter::class, $this->app->make(EmailFailureAlerter::class));
    }

    /**
     * The digest must be logged at CRITICAL: the prod 'slack' channel defaults
     * to level 'critical' (env LOG_LEVEL), so an ERROR-level record would be
     * silently filtered out and never reach Slack.
     */
    public function testLogAlerterLogsAtCriticalSoSlackThresholdPasses(): void
    {
        config(['mail-alerts.log_channel' => 'slack']);

        $digest = new EmailFailureDigest(2, ['App\\Mail\\X' => 2], ['Throttling' => 2], ['a@example.org']);

        $channel = Mockery::mock();
        $channel->shouldReceive('critical')->once()->with($digest->summaryLine(), $digest->context());
        $channel->shouldNotReceive('error');

        Log::shouldReceive('channel')->once()->with('slack')->andReturn($channel);

        (new LogChannelAlerter())->alert($digest);
    }
}
