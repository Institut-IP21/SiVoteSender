<?php

namespace Tests\Feature;

use App\Models\GlobalEmailBlockList;
use App\Models\SentMessage;
use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sending invites validates everything first: either every voter is queued or none.
 */
class SendInvitesAtomicTest extends TestCase
{
    use RefreshDatabase;

    private string $owner = 'a2c88f6a-f437-4080-80f0-fe40f596c050';

    /** @param array<int, array<string, mixed>> $voterAttrs */
    private function listWith(array $voterAttrs): VoterList
    {
        $list = VoterList::factory()->create(['owner' => $this->owner]);
        foreach ($voterAttrs as $attrs) {
            $list->voters()->attach(Voter::factory()->create($attrs)->id);
        }

        return $list;
    }

    private function send(VoterList $list, int $codeCount, string $path = 'send-invites'): \Illuminate\Testing\TestResponse
    {
        $payload = [
            'batch'    => Str::uuid()->toString(),
            'codes'    => array_map(fn () => Str::uuid()->toString(), range(1, $codeCount)),
            'template' => 'Code %%CODE%%',
            'subject'  => 'Subject',
        ];
        if ($path === 'send-invites') {
            $payload['url'] = 'http://x.test/%%CODE%%';
        } else {
            $payload['codes'] = array_map(fn ($c) => ['code' => $c, 'voter' => 'v', 'access_url' => 'u'], $payload['codes']);
        }

        return $this->withHeaders(['Authorization' => $this->token, 'Owner' => $this->owner])
            ->postJson('/api/voterlist/' . $list->id . '/' . $path, $payload);
    }

    private function assertNothingQueued(): void
    {
        $this->assertSame(0, SentMessage::count());
        Queue::assertNothingPushed();
    }

    public function test_voter_without_email_fails_422_and_queues_nothing(): void
    {
        Queue::fake();
        $list = $this->listWith([['email' => 'a@example.org'], ['email' => null], ['email' => 'c@example.org']]);

        $this->send($list, 3)
            ->assertStatus(422)
            ->assertJsonPath('problems.without_email', 1)
            ->assertJsonPath('problems.blocked', 0)
            ->assertJsonPath('problems.count_mismatch', false)
            ->assertJsonStructure(['message', 'errors' => ['voters'], 'problems']);

        $this->assertNothingQueued();
    }

    public function test_blocked_voter_fails_422_and_queues_nothing(): void
    {
        Queue::fake();
        $list = $this->listWith([['email' => 'a@example.org'], ['email' => 'Bad@example.org']]);
        GlobalEmailBlockList::create(['email' => 'bad@example.org', 'status' => 'bounce']);

        $this->send($list, 2)
            ->assertStatus(422)
            ->assertJsonPath('problems.blocked', 1);

        $this->assertNothingQueued();
    }

    public function test_voter_flagged_email_blocked_fails_422(): void
    {
        Queue::fake();
        $list = $this->listWith([['email' => 'a@example.org'], ['email' => 'b@example.org', 'email_blocked' => true]]);

        $this->send($list, 2)->assertStatus(422)->assertJsonPath('problems.blocked', 1);

        $this->assertNothingQueued();
    }

    public function test_code_count_mismatch_fails_422_and_queues_nothing(): void
    {
        Queue::fake();
        $list = $this->listWith([['email' => 'a@example.org'], ['email' => 'b@example.org']]);

        $this->send($list, 3)
            ->assertStatus(422)
            ->assertJsonPath('problems.count_mismatch', true)
            ->assertJsonPath('problems.without_email', 0);

        $this->assertNothingQueued();
    }

    public function test_session_invites_are_validated_the_same_way(): void
    {
        Queue::fake();
        $list = $this->listWith([['email' => null], ['email' => 'b@example.org']]);

        $this->send($list, 1, 'send-session-invites')
            ->assertStatus(422)
            ->assertJsonPath('problems.without_email', 1)
            ->assertJsonPath('problems.count_mismatch', true);

        $this->assertNothingQueued();
    }

    public function test_a_failure_while_queuing_rolls_everything_back(): void
    {
        Queue::fake();
        $list = $this->listWith([['email' => 'a@example.org'], ['email' => 'b@example.org']]);

        $dispatches = 0;
        \App\Models\SentMessage::creating(function () use (&$dispatches): void {
            if (++$dispatches === 2) {
                throw new \RuntimeException('boom');
            }
        });

        $this->send($list, 2)->assertStatus(500);

        $this->assertSame(0, SentMessage::count());
    }

    public function test_valid_list_still_sends(): void
    {
        Queue::fake();
        $list = $this->listWith([['email' => 'a@example.org'], ['email' => 'b@example.org']]);

        $this->send($list, 2)->assertOk();

        $this->assertSame(2, SentMessage::count());
    }

    public function test_repeating_the_same_batch_is_refused_and_queues_nothing_more(): void
    {
        Queue::fake();
        $list = $this->listWith([['email' => 'a@example.org'], ['email' => 'b@example.org']]);
        $batch = Str::uuid()->toString();
        $payload = [
            'batch' => $batch, 'codes' => ['c1', 'c2'], 'template' => 'T', 'subject' => 'S', 'url' => 'http://x.test/%%CODE%%',
        ];
        $h = ['Authorization' => $this->token, 'Owner' => $this->owner];

        $this->withHeaders($h)->postJson('/api/voterlist/' . $list->id . '/send-invites', $payload)->assertOk();
        $this->assertSame(2, SentMessage::count());

        $this->withHeaders($h)->postJson('/api/voterlist/' . $list->id . '/send-invites', $payload)
            ->assertStatus(409)
            ->assertJsonPath('error', 'already_sent');

        $this->assertSame(2, SentMessage::count());
    }

    public function test_a_different_batch_is_not_blocked(): void
    {
        Queue::fake();
        $list = $this->listWith([['email' => 'a@example.org']]);
        $h = ['Authorization' => $this->token, 'Owner' => $this->owner];
        $base = ['codes' => ['c1'], 'template' => 'T', 'subject' => 'S', 'url' => 'http://x.test/%%CODE%%'];

        $this->withHeaders($h)->postJson('/api/voterlist/' . $list->id . '/send-invites', $base + ['batch' => Str::uuid()->toString()])->assertOk();
        $this->withHeaders($h)->postJson('/api/voterlist/' . $list->id . '/send-invites', $base + ['batch' => Str::uuid()->toString()])->assertOk();
    }

    public function test_send_job_is_dispatched_after_commit(): void
    {
        $this->assertTrue((new \App\Jobs\SendVoterEmail('a@example.org', new \Illuminate\Mail\Mailable()))->afterCommit);
    }
}
