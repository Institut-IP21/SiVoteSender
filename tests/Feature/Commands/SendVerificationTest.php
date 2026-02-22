<?php

namespace Tests\Feature\Commands;

use App\Models\Verification;
use App\Models\VoterList;
use App\Services\Verification as VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function testSendVerificationSuccess()
    {
        $voterList = VoterList::factory()->create(['title' => 'Verify List']);
        $verification = Verification::factory()->create([
            'voterlist_id' => $voterList->id,
            'subject' => 'Verify',
        ]);

        $mock = Mockery::mock(VerificationService::class);
        $mock->shouldReceive('sendInvites')
            ->once()
            ->with(Mockery::on(function ($v) use ($verification) {
                return $v->id === $verification->id;
            }))
            ->andReturn(true);

        $this->app->instance(VerificationService::class, $mock);

        $this->artisan('evote:send:verification', [
            '--verification' => $verification->id,
        ])
            ->expectsConfirmation("Send verification emails to all voters in list 'Verify List'?", 'yes')
            ->expectsOutput('Verification emails queued for sending.')
            ->assertExitCode(0);
    }

    public function testSendVerificationCancelled()
    {
        $voterList = VoterList::factory()->create(['title' => 'Cancel List']);
        $verification = Verification::factory()->create([
            'voterlist_id' => $voterList->id,
            'subject' => 'Verify',
        ]);

        $this->artisan('evote:send:verification', [
            '--verification' => $verification->id,
        ])
            ->expectsConfirmation("Send verification emails to all voters in list 'Cancel List'?", 'no')
            ->expectsOutput('Cancelled.')
            ->assertExitCode(0);
    }

    public function testSendVerificationNotFound()
    {
        $this->artisan('evote:send:verification', [
            '--verification' => 999,
        ])
            ->expectsOutput('Verification with ID 999 not found.')
            ->assertExitCode(1);
    }
}
