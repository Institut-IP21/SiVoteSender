<?php

namespace Tests\Feature\Commands;

use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoterListShowTest extends TestCase
{
    use RefreshDatabase;

    public function testShowDetails()
    {
        $voterList = VoterList::factory()->create(['title' => 'Show Test']);

        $this->artisan('evote:show:voterlist', ['--voterlist' => $voterList->id])
            ->expectsTable(['Field', 'Value'], [
                ['ID', $voterList->id],
                ['Title', 'Show Test'],
                ['Owner', $voterList->owner],
                ['Voters', 0],
                ['Verified Voters', 0],
                ['Sent Messages', 0],
                ['Created At', $voterList->created_at],
            ])
            ->assertExitCode(0);
    }

    public function testShowWithVoters()
    {
        $voterList = VoterList::factory()->create(['title' => 'With Voters']);
        $voter1 = Voter::factory()->create();
        $voter2 = Voter::factory()->verifiedEmail()->create();
        $voterList->voters()->attach([$voter1->id, $voter2->id]);

        $this->artisan('evote:show:voterlist', ['--voterlist' => $voterList->id])
            ->expectsTable(['Field', 'Value'], [
                ['ID', $voterList->id],
                ['Title', 'With Voters'],
                ['Owner', $voterList->owner],
                ['Voters', 2],
                ['Verified Voters', 1],
                ['Sent Messages', 0],
                ['Created At', $voterList->created_at],
            ])
            ->assertExitCode(0);
    }

    public function testShowNotFound()
    {
        $this->artisan('evote:show:voterlist', ['--voterlist' => 999])
            ->expectsOutput('Voter list with ID 999 not found.')
            ->assertExitCode(1);
    }
}
