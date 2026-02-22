<?php

namespace Tests\Feature\Commands;

use App\Models\SentMessage;
use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchStatsTest extends TestCase
{
    use RefreshDatabase;

    public function testDisplayStats()
    {
        $voterList = VoterList::factory()->create();
        $voter1 = Voter::factory()->create();
        $voter2 = Voter::factory()->create();
        $voter3 = Voter::factory()->create();

        $batchUuid = 'stats-batch-uuid';

        SentMessage::factory()->create([
            'voter_id' => $voter1->id,
            'voterlist_id' => $voterList->id,
            'batch_uuid' => $batchUuid,
            'successful' => true,
            'status' => SentMessage::STATUS_SENT,
        ]);
        SentMessage::factory()->create([
            'voter_id' => $voter2->id,
            'voterlist_id' => $voterList->id,
            'batch_uuid' => $batchUuid,
            'successful' => true,
            'status' => SentMessage::STATUS_SENT,
        ]);
        SentMessage::factory()->notSuccessful()->create([
            'voter_id' => $voter3->id,
            'voterlist_id' => $voterList->id,
            'batch_uuid' => $batchUuid,
            'status' => SentMessage::STATUS_BLOCKED,
        ]);

        $this->artisan('evote:stats:batch', ['--batch' => $batchUuid])
            ->expectsTable(['Field', 'Value'], [
                ['Batch UUID', $batchUuid],
                ['Total Messages', 3],
                ['Successful', 2],
                ['Failed', 1],
            ])
            ->assertExitCode(0);
    }

    public function testNoMessagesError()
    {
        $this->artisan('evote:stats:batch', ['--batch' => 'nonexistent-batch'])
            ->expectsOutput('No messages found for batch nonexistent-batch')
            ->assertExitCode(1);
    }
}
