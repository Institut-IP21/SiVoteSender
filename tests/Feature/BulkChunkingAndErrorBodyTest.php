<?php

namespace Tests\Feature;

use App\Models\SentMessage;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class BulkChunkingAndErrorBodyTest extends TestCase
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
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $rows[] = ['title' => "V$i", 'email' => "v{$i}@example.org", 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('voters')->insert($rows);
        $ids = DB::table('voters')->where('email', 'like', 'v%@example.org')->whereNotIn('id', DB::table('voterlist_voter')->select('voter_id'))->pluck('id')->all();
        DB::table('voterlist_voter')->insert(array_map(fn ($id) => ['voterlist_id' => $list->id, 'voter_id' => $id, 'created_at' => now(), 'updated_at' => now()], $ids));

        return $list;
    }

    /** @param array<string, mixed> $extra */
    private function invite(VoterList $list, int $n, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->h())->postJson("/api/voterlist/{$list->id}/send-invites", $extra + [
            'batch' => Str::uuid()->toString(), 'codes' => array_map(fn ($i) => "code{$i}", range(1, $n)),
            'template' => 'T', 'subject' => 'S', 'url' => 'http://x.test/%%CODE%%',
        ]);
    }

    public function test_results_with_a_large_csv_are_inserted_in_statements_under_the_byte_cap(): void
    {
        // Dev-only: Ignition's query recorder retains every statement (~360 MB of payloads here).
        ini_set('memory_limit', '1G');
        config(['queue.default' => 'database']);
        $list = $this->listOf(1000);
        $csv = str_repeat(Str::random(99) . "\n", 2000); // ~200 KB

        $stmts = [];
        DB::listen(function ($q) use (&$stmts): void {
            if (str_contains($q->sql, 'into "jobs"')) {
                $stmts[] = array_sum(array_map(fn ($b) => is_string($b) ? strlen($b) : 8, $q->bindings));
            }
        });

        $this->withHeaders($this->h())->postJson("/api/voterlist/{$list->id}/send-results", [
            'batch' => Str::uuid()->toString(), 'template' => 'T', 'subject' => 'S', 'csv' => $csv, 'resultLink' => 'http://x.test/r',
        ])->assertOk();

        $this->assertSame(1000, DB::table('jobs')->count());
        $this->assertGreaterThan(10, count($stmts));
        $cap = 4 * 1024 * 1024;
        foreach ($stmts as $bytes) {
            // Only a single (unsplittable) job may exceed the cap; here one job is ~400 KB.
            $this->assertLessThanOrEqual($cap, $bytes);
        }
    }

    public function test_small_payloads_still_use_full_500_row_statements(): void
    {
        config(['queue.default' => 'database']);
        $list = $this->listOf(1200);
        $stmts = 0;
        DB::listen(function ($q) use (&$stmts): void {
            if (str_contains($q->sql, 'into "jobs"')) {
                $stmts++;
            }
        });

        $this->invite($list, 1200)->assertOk();

        $this->assertSame(3, $stmts);
    }

    public function test_a_500_never_returns_the_exception_message_and_logs_it_truncated(): void
    {
        config(['queue.default' => 'database', 'app.debug' => true]);
        $list = $this->listOf(2);
        $long = 'insert into secret_table values (' . str_repeat('x', 5000) . ')';
        DB::listen(function ($q) use ($long): void {
            if (str_contains($q->sql, 'into "jobs"')) {
                throw new \RuntimeException($long);
            }
        });
        $logged = [];
        Log::shouldReceive('alert')->andReturnUsing(function ($msg, $ctx = []) use (&$logged): void {
            $logged[] = json_encode($ctx);
        });
        Log::shouldReceive('info', 'warning', 'debug', 'error')->andReturnNull();

        $res = $this->invite($list, 2)->assertStatus(500);

        $this->assertSame('server_error', $res->json('error'));
        $this->assertStringNotContainsString('secret_table', $res->getContent());
        $this->assertNotEmpty($logged);
        $this->assertLessThan(1500, strlen($logged[0]));
    }

    public function test_unhandled_api_exceptions_render_a_generic_body_even_with_debug_on(): void
    {
        config(['app.debug' => true]);
        $list = $this->listOf(1);
        // Force an exception outside the controller's own try/catch.
        DB::listen(function ($q): void {
            if (str_contains($q->sql, 'voterlist_voter') && str_contains($q->sql, 'select')) {
                throw new \Illuminate\Database\QueryException('mysql', 'select secret_col from t', [], new \PDOException('SQLSTATE boom secret_col'));
            }
        });

        $res = $this->withHeaders($this->h())->getJson("/api/voterlist/{$list->id}/voters");

        $this->assertSame(500, $res->status());
        $this->assertSame(['error' => 'server_error'], $res->json());
    }

    public function test_send_invites_refuses_after_the_deadline_and_sends_nothing(): void
    {
        config(['queue.default' => 'database']);
        $list = $this->listOf(2);

        $this->invite($list, 2, ['deadline' => time() - 1])
            ->assertStatus(409)->assertJsonPath('error', 'deadline_passed');

        $this->assertSame(0, SentMessage::count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_send_invites_before_the_deadline_or_without_one_still_sends(): void
    {
        $list = $this->listOf(2);
        $this->invite($list, 2, ['deadline' => time() + 30])->assertOk();
        $list2 = $this->listOf(1);
        $this->invite($list2, 1)->assertOk();
    }

    public function test_remove_voters_locks_the_list_row_first_inside_a_transaction(): void
    {
        $list = $this->listOf(2);
        $victim = $list->voters()->first();
        $seen = [];
        DB::listen(function ($q) use (&$seen): void {
            $seen[] = [DB::transactionLevel(), $q->sql];
        });

        $this->withHeaders($this->h())->deleteJson("/api/voterlist/{$list->id}/voters", ['voters' => json_encode([$victim->id])])->assertOk();

        $lock = $firstVoterTouch = null;
        foreach ($seen as $i => [$level, $s]) {
            if ($lock === null && $level > 0 && str_contains($s, 'from "voterlists"')) {
                $lock = $i;
            }
            if ($firstVoterTouch === null && (str_contains($s, 'voterlist_voter') || str_contains($s, '"voters"')) && !str_contains($s, 'from "voterlists"')) {
                $firstVoterTouch = $i;
            }
        }
        $this->assertNotNull($lock, 'list lock missing / outside transaction');
        $this->assertNotNull($firstVoterTouch);
        $this->assertLessThan($firstVoterTouch, $lock);
    }
}
