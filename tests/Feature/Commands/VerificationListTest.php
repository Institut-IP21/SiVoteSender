<?php

namespace Tests\Feature\Commands;

use App\Models\Verification;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationListTest extends TestCase
{
    use RefreshDatabase;

    public function testEmptyList()
    {
        $this->artisan('evote:list:verification')
            ->expectsOutput('No verifications found.')
            ->assertExitCode(0);
    }

    public function testWithEntries()
    {
        $voterList = VoterList::factory()->create();
        $verification = Verification::factory()->create([
            'voterlist_id' => $voterList->id,
            'subject' => 'Verify Email',
        ]);

        $this->artisan('evote:list:verification')
            ->expectsTable(['ID', 'Voter List ID', 'Subject', 'Sent At'], [
                [$verification->id, $voterList->id, 'Verify Email', null],
            ])
            ->assertExitCode(0);
    }

    public function testFilterByVoterList()
    {
        $voterListA = VoterList::factory()->create();
        $voterListB = VoterList::factory()->create();
        Verification::factory()->create([
            'voterlist_id' => $voterListA->id,
            'subject' => 'Verification A',
        ]);
        Verification::factory()->create([
            'voterlist_id' => $voterListB->id,
            'subject' => 'Verification B',
        ]);

        $this->artisan('evote:list:verification', ['--voterlist' => $voterListA->id])
            ->expectsTable(['ID', 'Voter List ID', 'Subject', 'Sent At'], [
                [1, $voterListA->id, 'Verification A', null],
            ])
            ->assertExitCode(0);
    }
}
