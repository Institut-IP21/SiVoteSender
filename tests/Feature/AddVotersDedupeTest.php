<?php

namespace Tests\Feature;

use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddVotersDedupeTest extends TestCase
{
    use RefreshDatabase;

    private string $owner = 'c1c88f6a-f437-4080-80f0-fe40f596c050';

    private function add(VoterList $list, array $voters): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['Authorization' => $this->token, 'Owner' => $this->owner])
            ->postJson('/api/voterlist/' . $list->id . '/voters', ['voters' => json_encode($voters)]);
    }

    private function list(): VoterList
    {
        return VoterList::create(['title' => 'L', 'owner' => $this->owner]);
    }

    public function test_duplicate_emails_within_payload_are_skipped_case_insensitively(): void
    {
        $list = $this->list();

        $res = $this->add($list, [
            ['title' => 'A', 'email' => 'a@example.org'],
            ['title' => 'A again', 'email' => 'A@Example.ORG'],
            ['title' => 'B', 'email' => 'b@example.org'],
        ]);

        $res->assertOk();
        $res->assertJsonPath('added', 2);
        $res->assertJsonPath('skipped_duplicates', 1);
        $res->assertJsonPath('data.added', 2);
        $res->assertJsonPath('data.skipped_duplicates', 1);
        $this->assertSame(2, $list->voters()->count());
    }

    public function test_emails_already_on_the_list_are_skipped(): void
    {
        $list = $this->list();
        $this->add($list, [['title' => 'A', 'email' => 'a@example.org']])->assertOk();

        $res = $this->add($list, [
            ['title' => 'A2', 'email' => 'A@example.org'],
            ['title' => 'C', 'email' => 'c@example.org'],
        ]);

        $res->assertJsonPath('added', 1);
        $res->assertJsonPath('skipped_duplicates', 1);
        $this->assertSame(2, $list->voters()->count());
        $this->assertSame(2, Voter::count());
        // response keeps its existing shape
        $res->assertJsonStructure(['data' => ['id', 'title', 'owner', 'voters', 'stats']]);
    }

    public function test_same_email_on_a_different_list_is_still_allowed(): void
    {
        $one = $this->list();
        $two = $this->list();
        $this->add($one, [['title' => 'A', 'email' => 'a@example.org']])->assertOk();

        $this->add($two, [['title' => 'A', 'email' => 'a@example.org']])->assertJsonPath('added', 1);

        $this->assertSame(1, $two->voters()->count());
    }

    public function test_phone_only_rows_dedupe_on_normalised_phone(): void
    {
        $list = $this->list();

        $res = $this->add($list, [
            ['title' => 'P1', 'phone' => '+386 40 123 456'],
            ['title' => 'P1 dup', 'phone' => '+386-40-123-456'],
            ['title' => 'P2', 'phone' => '040 123 456'],
        ]);

        $res->assertJsonPath('added', 2);
        $res->assertJsonPath('skipped_duplicates', 1);
    }

    public function test_rows_with_neither_email_nor_phone_are_not_deduped(): void
    {
        $list = $this->list();

        $res = $this->add($list, [['title' => 'X'], ['title' => 'Y']]);

        $res->assertJsonPath('added', 2);
        $res->assertJsonPath('skipped_duplicates', 0);
    }

    public function test_a_mid_batch_failure_leaves_no_partial_batch(): void
    {
        $list = $this->list();

        // Force the second insert to blow up.
        $calls = 0;
        Voter::creating(function () use (&$calls): void {
            if (++$calls === 2) {
                throw new \RuntimeException('boom');
            }
        });

        $res = $this->add($list, [
            ['title' => 'A', 'email' => 'a@example.org'],
            ['title' => 'B', 'email' => 'b@example.org'],
        ]);

        $res->assertStatus(500);
        $this->assertSame(0, Voter::count());
        $this->assertSame(0, $list->voters()->count());
    }
}
