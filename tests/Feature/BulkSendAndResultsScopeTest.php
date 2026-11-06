<?php

namespace Tests\Feature;

use App\Models\GlobalEmailBlockList;
use App\Models\SentMessage;
use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Bulk (constant-query) sends, the locking batch-exists check, and results scoped to the invited set.
 */
class BulkSendAndResultsScopeTest extends TestCase
{
    use RefreshDatabase;

    private string $owner = 'a2c88f6a-f437-4080-80f0-fe40f596c050';

    /** @return array<string, string> */
    private function h(): array
    {
        return ['Authorization' => $this->token, 'Owner' => $this->owner];
    }

    private function listOf(int $n): VoterList
    {
        $list = VoterList::factory()->create(['owner' => $this->owner]);
        for ($i = 0; $i < $n; $i++) {
            $list->voters()->attach(Voter::factory()->create(['email' => "v{$i}@example.org"])->id);
        }

        return $list;
    }

    /** @return \Illuminate\Testing\TestResponse<\Illuminate\Http\JsonResponse> */
    private function invite(VoterList $list, string $batch, int $n)
    {
        return $this->withHeaders($this->h())->postJson("/api/voterlist/{$list->id}/send-invites", [
            'batch' => $batch, 'codes' => array_map(fn ($i) => "code{$i}", range(1, $n)),
            'template' => 'T', 'subject' => 'S', 'url' => 'http://x.test/%%CODE%%',
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function results(VoterList $list, string $batch, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->h())->postJson("/api/voterlist/{$list->id}/send-results", $extra + [
            'batch' => $batch, 'template' => 'T', 'subject' => 'S', 'csv' => 'a,b', 'resultLink' => 'http://x.test/r',
        ]);
    }

    /** @return list<string> emails of the voters that got a message in the batch */
    private function recipients(string $batch): array
    {
        return SentMessage::where('batch_uuid', $batch)->with('voter')->get()->map(fn ($m) => $m->voter->email)->sort()->values()->all();
    }

    public function test_batch_exists_locks_the_list_row_inside_a_transaction_before_counting(): void
    {
        $list = $this->listOf(1);
        $seen = [];
        DB::listen(function ($q) use (&$seen): void {
            $seen[] = [DB::transactionLevel(), $q->sql];
        });

        $this->withHeaders($this->h())->getJson("/api/voterlist/{$list->id}/batch/" . Str::uuid() . '/exists')->assertOk();

        $lockIdx = null;
        $countIdx = null;
        foreach ($seen as $i => [$level, $s]) {
            // (sqlite drops the FOR UPDATE suffix; the call path is what we can assert here.)
            if ($lockIdx === null && $level > 0 && str_contains($s, 'from "voterlists"')) {
                $lockIdx = $i;
            }
            if (str_contains($s, 'sent_messages') && str_contains($s, 'count(')) {
                $countIdx = $i;
            }
        }
        $this->assertNotNull($countIdx, 'count query missing');
        $this->assertNotNull($lockIdx, 'list row lock query missing');
        $this->assertLessThan($countIdx, $lockIdx);
        $this->assertGreaterThan(0, $seen[$countIdx][0], 'count must run inside a transaction');
    }

    public function test_bulk_send_uses_constant_queries_and_one_job_row_per_voter(): void
    {
        config(['queue.default' => 'database']);
        $list = $this->listOf(60);
        GlobalEmailBlockList::create(['email' => 'unrelated@example.org', 'status' => 'bounce']);

        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });
        $this->invite($list, $batch = Str::uuid()->toString(), 60)->assertOk();

        $this->assertLessThan(40, $count, "send used $count queries");
        $this->assertSame(60, SentMessage::where('batch_uuid', $batch)->count());
        $this->assertSame(60, DB::table('jobs')->count());
        $this->assertSame(60, SentMessage::where('batch_uuid', $batch)->distinct('voter_id')->count('voter_id'));
    }

    public function test_bulk_send_inserts_jobs_inside_the_send_transaction(): void
    {
        // Laravel's DatabaseQueue::bulk defers afterCommit jobs until after the commit;
        // the send must opt out so a crash can't leave messages "sent" with no job.
        config(['queue.default' => 'database']);
        $list = $this->listOf(3);
        $base = DB::transactionLevel();
        $levels = [];
        DB::listen(function ($q) use (&$levels): void {
            if (str_contains($q->sql, 'into "jobs"')) {
                $levels[] = DB::transactionLevel();
            }
        });

        $this->invite($list, Str::uuid()->toString(), 3)->assertOk();

        $this->assertNotEmpty($levels);
        foreach ($levels as $level) {
            $this->assertGreaterThan($base, $level, 'jobs were inserted after the send transaction committed');
        }
    }

    public function test_bulk_send_rolls_back_jobs_and_messages_together(): void
    {
        config(['queue.default' => 'database']);
        $list = $this->listOf(3);
        // Fail right after the jobs are inserted, before commit.
        DB::listen(function ($q): void {
            if (str_contains($q->sql, 'into "jobs"')) {
                throw new \RuntimeException('boom');
            }
        });

        $this->invite($list, Str::uuid()->toString(), 3)->assertStatus(500);

        $this->assertSame(0, SentMessage::count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_results_with_invite_batch_go_only_to_the_invited_set(): void
    {
        Queue::fake();
        $list = $this->listOf(2);
        $this->invite($list, $inviteBatch = Str::uuid()->toString(), 2)->assertOk();

        // Added after the invite: must not get results.
        $late = Voter::factory()->create(['email' => 'late@example.org']);
        $list->voters()->attach($late->id);
        // Detached (but the row still exists) after the invite: must get results.
        $leaver = $list->voters()->where('email', 'v0@example.org')->first();
        $list->voters()->detach($leaver->id);

        $this->results($list, $rb = Str::uuid()->toString(), ['invite_batch' => $inviteBatch])->assertOk();

        $this->assertSame(['v0@example.org', 'v1@example.org'], $this->recipients($rb));
    }

    public function test_results_skip_a_voter_who_was_removed_through_the_api(): void
    {
        Queue::fake();
        $list = $this->listOf(2);
        $this->invite($list, $inviteBatch = Str::uuid()->toString(), 2)->assertOk();
        $gone = $list->voters()->where('email', 'v0@example.org')->first();
        $this->withHeaders($this->h())->deleteJson("/api/voterlist/{$list->id}/voters", ['voters' => json_encode([$gone->id])])->assertOk();

        $this->results($list, $rb = Str::uuid()->toString(), ['invite_batch' => $inviteBatch])->assertOk();

        $this->assertSame(['v1@example.org'], $this->recipients($rb));
    }

    public function test_results_without_invite_batch_still_go_to_current_members(): void
    {
        Queue::fake();
        $list = $this->listOf(2);
        $this->invite($list, Str::uuid()->toString(), 2)->assertOk();
        $list->voters()->attach(Voter::factory()->create(['email' => 'late@example.org'])->id);

        $this->results($list, $rb = Str::uuid()->toString())->assertOk();

        $this->assertSame(['late@example.org', 'v0@example.org', 'v1@example.org'], $this->recipients($rb));
    }

    public function test_results_with_unknown_invite_batch_are_refused_and_send_nothing(): void
    {
        Queue::fake();
        $list = $this->listOf(2);

        $this->results($list, Str::uuid()->toString(), ['invite_batch' => Str::uuid()->toString()])
            ->assertStatus(422)->assertJsonPath('error', 'unknown_invite_batch');

        $this->assertSame(0, SentMessage::count());
    }

    public function test_results_skip_blocked_addresses_like_before(): void
    {
        Queue::fake();
        $list = $this->listOf(2);
        $this->invite($list, $inviteBatch = Str::uuid()->toString(), 2)->assertOk();
        Voter::where('email', 'v0@example.org')->update(['email_blocked' => true]);
        GlobalEmailBlockList::create(['email' => 'v1@example.org', 'status' => 'bounce']);

        $this->results($list, $rb = Str::uuid()->toString(), ['invite_batch' => $inviteBatch])->assertOk();

        // email_blocked voter: no row at all; blocklisted: row marked blocked, no job.
        $this->assertSame(['v1@example.org'], $this->recipients($rb));
        $this->assertSame(SentMessage::STATUS_BLOCKED, SentMessage::where('batch_uuid', $rb)->value('status'));
    }

    public function test_results_batch_is_visible_to_batch_exists_and_a_repeat_is_refused(): void
    {
        Queue::fake();
        $list = $this->listOf(1);
        $rb = Str::uuid()->toString();
        $url = "/api/voterlist/{$list->id}/batch/{$rb}/exists";

        $this->withHeaders($this->h())->getJson($url)->assertExactJson(['exists' => false, 'count' => 0]);
        $this->results($list, $rb)->assertOk();
        $this->withHeaders($this->h())->getJson($url)->assertExactJson(['exists' => true, 'count' => 1]);

        $this->results($list, $rb)->assertStatus(409)->assertJsonPath('error', 'already_sent');
        $this->assertSame(1, SentMessage::where('batch_uuid', $rb)->count());
    }

    public function test_batch_exists_accepts_a_wait_cap(): void
    {
        $list = $this->listOf(1);

        $this->withHeaders($this->h())->getJson("/api/voterlist/{$list->id}/batch/" . Str::uuid() . '/exists?wait=5')
            ->assertOk()->assertExactJson(['exists' => false, 'count' => 0]);
    }
}
