<?php

namespace Tests\Feature\Commands;

use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoterListDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function testDeleteSuccess(): void
    {
        $voterList = VoterList::factory()->create(['title' => 'To Delete']);

        $this->artisan('evote:delete:voterlist', ['--voterlist' => $voterList->id])
            ->expectsConfirmation("Are you sure you want to delete voter list 'To Delete'?", 'yes')
            ->expectsOutput("Voter list 'To Delete' has been deleted.")
            ->assertExitCode(0);

        $this->assertSoftDeleted('voterlists', ['id' => $voterList->id]);
    }

    public function testDeleteRequiresConfirmation(): void
    {
        $voterList = VoterList::factory()->create(['title' => 'Keep Me']);

        $this->artisan('evote:delete:voterlist', ['--voterlist' => $voterList->id])
            ->expectsConfirmation("Are you sure you want to delete voter list 'Keep Me'?", 'no')
            ->expectsOutput('Cancelled.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('voterlists', [
            'id' => $voterList->id,
            'deleted_at' => null,
        ]);
    }

    public function testDeleteNotFound(): void
    {
        $this->artisan('evote:delete:voterlist', ['--voterlist' => 999])
            ->expectsOutput('Voter list with ID 999 not found.')
            ->assertExitCode(1);
    }
}
