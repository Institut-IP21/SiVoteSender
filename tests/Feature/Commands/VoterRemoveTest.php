<?php

namespace Tests\Feature\Commands;

use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoterRemoveTest extends TestCase
{
    use RefreshDatabase;

    public function testRemoveSuccess(): void
    {
        $voter = Voter::factory()->create(['title' => 'To Remove']);

        $this->artisan('evote:remove:voter', ['--voter' => $voter->id])
            ->expectsConfirmation("Are you sure you want to remove voter 'To Remove'?", 'yes')
            ->expectsOutput("Voter 'To Remove' has been removed.")
            ->assertExitCode(0);

        $this->assertSoftDeleted('voters', ['id' => $voter->id]);
    }

    public function testRemoveRequiresConfirmation(): void
    {
        $voter = Voter::factory()->create(['title' => 'Keep Me']);

        $this->artisan('evote:remove:voter', ['--voter' => $voter->id])
            ->expectsConfirmation("Are you sure you want to remove voter 'Keep Me'?", 'no')
            ->expectsOutput('Cancelled.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('voters', [
            'id' => $voter->id,
            'deleted_at' => null,
        ]);
    }

    public function testRemoveNotFound(): void
    {
        $this->artisan('evote:remove:voter', ['--voter' => 999])
            ->expectsOutput('Voter with ID 999 not found.')
            ->assertExitCode(1);
    }
}
