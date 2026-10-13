<?php

namespace Tests\Feature;

use App\Models\GlobalEmailBlockList;
use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaunchCheckTest extends TestCase
{
    use RefreshDatabase;

    private string $owner = 'a2c88f6a-f437-4080-80f0-fe40f596c050';

    private function get_(VoterList $list, ?string $owner = null): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['Authorization' => $this->token, 'Owner' => $owner ?? $this->owner])
            ->getJson('/api/voterlist/' . $list->id . '/launch-check');
    }

    public function test_reports_totals_without_email_blocked_and_duplicates(): void
    {
        $list = VoterList::factory()->create(['owner' => $this->owner]);
        foreach ([
            ['email' => 'a@example.org'],
            ['email' => 'A@example.org'],            // duplicate of the first
            ['email' => 'b@example.org', 'email_blocked' => true],
            ['email' => 'c@example.org'],            // on global block list
            ['email' => null, 'phone' => '+386 40 111 222'],
            ['email' => null, 'phone' => '+386-40-111-222'], // phone duplicate
            ['email' => null, 'phone' => null],
        ] as $attrs) {
            $list->voters()->attach(Voter::factory()->create($attrs)->id);
        }
        GlobalEmailBlockList::create(['email' => 'c@example.org', 'status' => 'bounce']);

        $this->get_($list)->assertOk()->assertExactJson([
            'total'         => 7,
            'without_email' => 3,
            'blocked'       => 2,
            'duplicates'    => 2,
        ]);
    }

    public function test_empty_list(): void
    {
        $list = VoterList::factory()->create(['owner' => $this->owner]);

        $this->get_($list)->assertOk()->assertExactJson([
            'total' => 0, 'without_email' => 0, 'blocked' => 0, 'duplicates' => 0,
        ]);
    }

    public function test_other_owner_cannot_read_the_check(): void
    {
        $list = VoterList::factory()->create(['owner' => $this->owner]);

        $this->get_($list, 'b0000000-0000-4000-8000-000000000000')->assertStatus(403);
    }
}
