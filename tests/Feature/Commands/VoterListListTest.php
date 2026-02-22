<?php

namespace Tests\Feature\Commands;

use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoterListListTest extends TestCase
{
    use RefreshDatabase;

    public function testEmptyList()
    {
        $this->artisan('evote:list:voterlist')
            ->expectsOutput('No voter lists found.')
            ->assertExitCode(0);
    }

    public function testListWithEntries()
    {
        VoterList::factory()->create(['title' => 'List A']);
        VoterList::factory()->create(['title' => 'List B']);

        $this->artisan('evote:list:voterlist')
            ->expectsTable(['ID', 'Title', 'Voters', 'Created At'], [
                [1, 'List A', 0, VoterList::find(1)->created_at],
                [2, 'List B', 0, VoterList::find(2)->created_at],
            ])
            ->assertExitCode(0);
    }

    public function testFilterByOwner()
    {
        VoterList::factory()->create(['title' => 'Owner A List', 'owner' => 'owner-a']);
        VoterList::factory()->create(['title' => 'Owner B List', 'owner' => 'owner-b']);

        $this->artisan('evote:list:voterlist', ['--owner' => 'owner-a'])
            ->expectsTable(['ID', 'Title', 'Voters', 'Created At'], [
                [1, 'Owner A List', 0, VoterList::find(1)->created_at],
            ])
            ->assertExitCode(0);
    }
}
