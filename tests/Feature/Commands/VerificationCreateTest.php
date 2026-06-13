<?php

namespace Tests\Feature\Commands;

use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationCreateTest extends TestCase
{
    use RefreshDatabase;

    public function testCreateWithOptions(): void
    {
        $voterList = VoterList::factory()->create(['title' => 'My List']);

        $this->artisan('evote:make:verification', [
            '--voterlist' => $voterList->id,
            '--template' => 'Click here: %%LINK%%',
            '--subject' => 'Verify your email',
            '--redirect-url' => 'https://example.com/done',
        ])
            ->expectsOutput("Created verification for voter list 'My List' with ID 1")
            ->assertExitCode(0);

        $this->assertDatabaseHas('verifications', [
            'voterlist_id' => $voterList->id,
            'template' => 'Click here: %%LINK%%',
            'subject' => 'Verify your email',
            'redirect_url' => 'https://example.com/done',
        ]);
    }

    public function testCreateInteractivePrompts(): void
    {
        $voterList = VoterList::factory()->create(['title' => 'Prompted List']);

        $this->artisan('evote:make:verification', [
            '--voterlist' => $voterList->id,
        ])
            ->expectsQuestion('Enter email subject', 'Please verify')
            ->expectsQuestion('Enter email template (must contain %%LINK%%)', 'Verify: %%LINK%%')
            ->assertExitCode(0);

        $this->assertDatabaseHas('verifications', [
            'voterlist_id' => $voterList->id,
            'subject' => 'Please verify',
            'template' => 'Verify: %%LINK%%',
        ]);
    }

    public function testCreateFailsWithoutLink(): void
    {
        $voterList = VoterList::factory()->create();

        $this->artisan('evote:make:verification', [
            '--voterlist' => $voterList->id,
            '--template' => 'No link here',
            '--subject' => 'Subject',
        ])
            ->expectsOutput('Template must contain %%LINK%%')
            ->assertExitCode(1);
    }

    public function testCreateFailsWithInvalidVoterList(): void
    {
        $this->artisan('evote:make:verification', [
            '--voterlist' => 999,
            '--template' => '%%LINK%%',
            '--subject' => 'Subject',
        ])
            ->expectsOutput('Voter list with ID 999 not found.')
            ->assertExitCode(1);
    }
}
