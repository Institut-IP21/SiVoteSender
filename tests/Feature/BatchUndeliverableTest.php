<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SentMessage;
use App\Models\VoterList;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchUndeliverableTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_bounced_complaint_rows_with_voter_and_reason(): void
    {
        $owner = fake()->uuid();
        $list = VoterList::factory()->create(['owner' => $owner]);
        $batch = 'ballot-uuid-1';

        $bounced = Voter::factory()->create(['email' => 'bad@x.test']);
        SentMessage::factory()->create(['voterlist_id' => $list->id, 'voter_id' => $bounced->id, 'batch_uuid' => $batch, 'type' => 'email', 'status' => SentMessage::STATUS_BOUNCE, 'status_msg' => 'no such user']);
        // a delivered one must NOT appear
        $ok = Voter::factory()->create(['email' => 'ok@x.test']);
        SentMessage::factory()->create(['voterlist_id' => $list->id, 'voter_id' => $ok->id, 'batch_uuid' => $batch, 'type' => 'email', 'status' => SentMessage::STATUS_DELIVERED]);

        $res = $this->getJson("/api/messages/batch/$batch/undeliverable", ['Authorization' => $this->token, 'Owner' => $owner]);

        $res->assertOk();
        $res->assertJsonCount(1, 'data');
        $res->assertJsonPath('data.0.email', 'bad@x.test');
        $res->assertJsonPath('data.0.status', SentMessage::STATUS_BOUNCE);
        $res->assertJsonPath('data.0.status_msg', 'no such user');
    }

    public function test_cross_owner_is_forbidden(): void
    {
        $list = VoterList::factory()->create(['owner' => fake()->uuid()]);
        $v = Voter::factory()->create();
        SentMessage::factory()->create(['voterlist_id' => $list->id, 'voter_id' => $v->id, 'batch_uuid' => 'b', 'type' => 'email', 'status' => SentMessage::STATUS_BOUNCE]);

        $this->getJson('/api/messages/batch/b/undeliverable', ['Authorization' => $this->token, 'Owner' => fake()->uuid()])
            ->assertStatus(403);
    }

    public function test_empty_when_no_bounces(): void
    {
        $owner = fake()->uuid();
        $list = VoterList::factory()->create(['owner' => $owner]);
        $v = Voter::factory()->create();
        SentMessage::factory()->create(['voterlist_id' => $list->id, 'voter_id' => $v->id, 'batch_uuid' => 'b', 'type' => 'email', 'status' => SentMessage::STATUS_DELIVERED]);

        $this->getJson('/api/messages/batch/b/undeliverable', ['Authorization' => $this->token, 'Owner' => $owner])
            ->assertOk()->assertJsonCount(0, 'data');
    }
}
