<?php

namespace Tests\Feature\Commands;

use App\Models\Voter;
use App\Models\VoterList;
use App\Services\Ballot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendInvitesTest extends TestCase
{
    use RefreshDatabase;

    public function testSendInvitesSuccess()
    {
        $voterList = VoterList::factory()->create(['title' => 'Invite List']);
        $voter1 = Voter::factory()->create(['email' => 'v1@example.com']);
        $voter2 = Voter::factory()->create(['email' => 'v2@example.com']);
        $voterList->voters()->attach([$voter1->id, $voter2->id]);

        $codes = ['CODE1', 'CODE2'];
        $codesPath = tempnam(sys_get_temp_dir(), 'codes');
        file_put_contents($codesPath, json_encode($codes));

        $templatePath = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($templatePath, '<p>Vote here: %%CODE%%</p>');

        $batchUuid = 'test-batch-uuid';

        $mock = Mockery::mock(Ballot::class);
        $mock->shouldReceive('sendInvites')
            ->once()
            ->with(
                Mockery::on(fn ($vl) => $vl->id === $voterList->id),
                $codes,
                'https://vote.example.com/%%CODE%%',
                $batchUuid,
                '<p>Vote here: %%CODE%%</p>',
                'You are invited to vote'
            )
            ->andReturn(true);

        $this->app->instance(Ballot::class, $mock);

        $this->artisan('evote:send:invites', [
            '--voterlist' => $voterList->id,
            '--codes' => $codesPath,
            '--template' => $templatePath,
            '--subject' => 'You are invited to vote',
            '--url' => 'https://vote.example.com/%%CODE%%',
            '--batch' => $batchUuid,
        ])
            ->expectsOutput("Ballot invites queued for sending (batch: {$batchUuid})")
            ->assertExitCode(0);

        unlink($codesPath);
        unlink($templatePath);
    }

    public function testSendInvitesCodeCountMismatch()
    {
        $voterList = VoterList::factory()->create();
        $voter = Voter::factory()->create();
        $voterList->voters()->attach($voter->id);

        $codesPath = tempnam(sys_get_temp_dir(), 'codes');
        file_put_contents($codesPath, json_encode(['CODE1', 'CODE2', 'CODE3']));

        $templatePath = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($templatePath, '<p>Template</p>');

        $this->artisan('evote:send:invites', [
            '--voterlist' => $voterList->id,
            '--codes' => $codesPath,
            '--template' => $templatePath,
            '--subject' => 'Subject',
            '--url' => 'https://vote.example.com/%%CODE%%',
        ])
            ->expectsOutput('Number of codes (3) does not match number of voters (1).')
            ->assertExitCode(1);

        unlink($codesPath);
        unlink($templatePath);
    }

    public function testSendInvitesUrlMissingCode()
    {
        $voterList = VoterList::factory()->create();

        $codesPath = tempnam(sys_get_temp_dir(), 'codes');
        file_put_contents($codesPath, json_encode(['CODE1']));

        $templatePath = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($templatePath, '<p>Template</p>');

        $this->artisan('evote:send:invites', [
            '--voterlist' => $voterList->id,
            '--codes' => $codesPath,
            '--template' => $templatePath,
            '--subject' => 'Subject',
            '--url' => 'https://vote.example.com/novariable',
        ])
            ->expectsOutput('Voting URL must contain %%CODE%%.')
            ->assertExitCode(1);

        unlink($codesPath);
        unlink($templatePath);
    }

    public function testSendInvitesMissingVoterList()
    {
        $this->artisan('evote:send:invites')
            ->expectsOutput('Voter list ID is required. Provide --voterlist.')
            ->assertExitCode(1);
    }
}
