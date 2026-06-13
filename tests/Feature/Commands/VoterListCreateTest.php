<?php

namespace Tests\Feature\Commands;

use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoterListCreateTest extends TestCase
{
    use RefreshDatabase;

    public function testCreateWithOptions(): void
    {
        $this->artisan('evote:make:voterlist', [
            '--title' => 'Test List',
            '--owner' => 'aaaa-bbbb-cccc',
        ])
            ->expectsOutput("Created voter list 'Test List' with ID 1")
            ->assertExitCode(0);

        $this->assertDatabaseHas('voterlists', [
            'title' => 'Test List',
            'owner' => 'aaaa-bbbb-cccc',
        ]);
    }

    public function testCreateWithDefaultOwner(): void
    {
        config(['app.cli.default_owner' => 'default-owner-uuid']);

        $this->artisan('evote:make:voterlist', [
            '--title' => 'Default Owner List',
        ])
            ->assertExitCode(0);

        $this->assertDatabaseHas('voterlists', [
            'title' => 'Default Owner List',
            'owner' => 'default-owner-uuid',
        ]);
    }

    public function testCreateInteractivePrompt(): void
    {
        config(['app.cli.default_owner' => 'default-owner-uuid']);

        $this->artisan('evote:make:voterlist')
            ->expectsQuestion('Enter voter list title', 'Prompted Title')
            ->assertExitCode(0);

        $this->assertDatabaseHas('voterlists', [
            'title' => 'Prompted Title',
            'owner' => 'default-owner-uuid',
        ]);
    }

    public function testCreateFailsWithoutOwner(): void
    {
        config(['app.cli.default_owner' => null]);

        $this->artisan('evote:make:voterlist', [
            '--title' => 'No Owner List',
        ])
            ->expectsOutput('Owner UUID is required. Provide --owner or set CLI_DEFAULT_OWNER in .env')
            ->assertExitCode(1);
    }
}
