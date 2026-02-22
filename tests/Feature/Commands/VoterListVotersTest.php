<?php

namespace Tests\Feature\Commands;

use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoterListVotersTest extends TestCase
{
    use RefreshDatabase;

    public function testEmptyVoterList()
    {
        $voterList = VoterList::factory()->create();

        $this->artisan('evote:list:voter', ['--voterlist' => $voterList->id])
            ->expectsOutput('No voters found in this list.')
            ->assertExitCode(0);
    }

    public function testWithVoters()
    {
        $voterList = VoterList::factory()->create();
        $voter1 = Voter::factory()->create(['title' => 'Alice', 'email' => 'alice@example.com']);
        $voter2 = Voter::factory()->verifiedEmail()->create(['title' => 'Bob', 'email' => 'bob@example.com', 'phone' => '+1234567890']);
        $voterList->voters()->attach([$voter1->id, $voter2->id]);

        $this->artisan('evote:list:voter', ['--voterlist' => $voterList->id])
            ->expectsTable(['ID', 'Name', 'Email', 'Phone', 'Email Verified'], [
                [$voter1->id, 'Alice', 'alice@example.com', null, 'No'],
                [$voter2->id, 'Bob', 'bob@example.com', '+1234567890', 'Yes'],
            ])
            ->assertExitCode(0);
    }

    public function testNotFound()
    {
        $this->artisan('evote:list:voter', ['--voterlist' => 999])
            ->expectsOutput('Voter list with ID 999 not found.')
            ->assertExitCode(1);
    }
}
