<?php

namespace Tests\Feature\Commands;

use App\Models\Voter;
use App\Models\VoterList;
use App\Services\Ballot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendResultsTest extends TestCase
{
    use RefreshDatabase;

    public function testSendResultsSuccess()
    {
        $voterList = VoterList::factory()->create(['title' => 'Results List']);
        $voter = Voter::factory()->create();
        $voterList->voters()->attach($voter->id);

        $templatePath = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($templatePath, '<p>Here are the results</p>');

        $csvPath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($csvPath, "candidate,votes\nAlice,100\nBob,80\n");

        $batchUuid = 'results-batch-uuid';

        $mock = Mockery::mock(Ballot::class);
        $mock->shouldReceive('sendResults')
            ->once()
            ->with(
                Mockery::on(fn ($vl) => $vl->id === $voterList->id),
                $batchUuid,
                '<p>Here are the results</p>',
                'Election Results',
                "candidate,votes\nAlice,100\nBob,80\n",
                'https://results.example.com'
            )
            ->andReturn(true);

        $this->app->instance(Ballot::class, $mock);

        $this->artisan('evote:send:results', [
            '--voterlist' => $voterList->id,
            '--template' => $templatePath,
            '--subject' => 'Election Results',
            '--csv' => $csvPath,
            '--result-link' => 'https://results.example.com',
            '--batch' => $batchUuid,
        ])
            ->expectsOutput("Result emails queued for sending (batch: {$batchUuid})")
            ->assertExitCode(0);

        unlink($templatePath);
        unlink($csvPath);
    }

    public function testSendResultsMissingVoterList()
    {
        $this->artisan('evote:send:results')
            ->expectsOutput('Voter list ID is required. Provide --voterlist.')
            ->assertExitCode(1);
    }

    public function testSendResultsMissingTemplate()
    {
        $voterList = VoterList::factory()->create();

        $this->artisan('evote:send:results', [
            '--voterlist' => $voterList->id,
        ])
            ->expectsOutput('Template HTML file is required and must exist. Provide --template.')
            ->assertExitCode(1);
    }
}
