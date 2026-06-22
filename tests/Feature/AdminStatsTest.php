<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EmailSendFailure;
use App\Models\GlobalEmailBlockList;
use App\Models\SentMessage;
use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStatsTest extends TestCase
{
    use RefreshDatabase;

    private string $apiToken = 'test-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.api.authlist' => [$this->apiToken]]);
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return [
            'Authorization' => $this->apiToken,
            'Owner'         => fake()->uuid(),
        ];
    }

    public function test_deliverability_stats_counts_statuses_and_global_tables(): void
    {
        $list = VoterList::factory()->create(['owner' => fake()->uuid()]);

        $statuses = [
            SentMessage::STATUS_SENT,
            SentMessage::STATUS_SENT,
            SentMessage::STATUS_DELIVERED,
            SentMessage::STATUS_BOUNCE_SOFT,
            SentMessage::STATUS_BOUNCE,
            SentMessage::STATUS_COMPLAINT,
            SentMessage::STATUS_BLOCKED,
        ];
        foreach ($statuses as $status) {
            $voter = Voter::factory()->create();
            $message = new SentMessage();
            $message->voterlist_id = $list->id;
            $message->voter_id     = $voter->id;
            $message->batch_uuid   = 'batch-x';
            $message->type         = SentMessage::TYPE_EMAIL;
            $message->status       = $status;
            $message->successful   = true;
            $message->save();
        }

        GlobalEmailBlockList::create(['email' => 'b1@example.org', 'status' => GlobalEmailBlockList::STATUS_BOUNCE]);
        GlobalEmailBlockList::create(['email' => 'b2@example.org', 'status' => GlobalEmailBlockList::STATUS_COMPLAINT]);

        EmailSendFailure::create(['recipient' => 'f1@example.org', 'error' => 'Throttling', 'attempts' => 8]);
        EmailSendFailure::create(['recipient' => 'f2@example.org', 'error' => 'Throttling', 'attempts' => 8]);
        EmailSendFailure::create(['recipient' => 'f3@example.org', 'error' => 'Throttling', 'attempts' => 8]);

        $res = $this->getJson('/api/admin/deliverability/stats', $this->authHeaders());

        $res->assertOk();
        $res->assertJsonPath('stats.' . SentMessage::STATUS_SENT, 2);
        $res->assertJsonPath('stats.' . SentMessage::STATUS_DELIVERED, 1);
        $res->assertJsonPath('stats.' . SentMessage::STATUS_BOUNCE_SOFT, 1);
        $res->assertJsonPath('stats.' . SentMessage::STATUS_BOUNCE, 1);
        $res->assertJsonPath('stats.' . SentMessage::STATUS_COMPLAINT, 1);
        $res->assertJsonPath('stats.' . SentMessage::STATUS_BLOCKED, 1);
        $res->assertJsonPath('stats.total', 7);
        $res->assertJsonPath('block_list_count', 2);
        $res->assertJsonPath('send_failures_count', 3);
    }

    public function test_admin_stats_returns_totals_and_twelve_month_trend(): void
    {
        VoterList::factory()->count(2)->create(['owner' => fake()->uuid()]);
        Voter::factory()->count(4)->create(['created_at' => now()]);

        $res = $this->getJson('/api/admin/stats', $this->authHeaders());

        $res->assertOk();
        $res->assertJsonPath('stats.voters_total', 4);
        $res->assertJsonPath('stats.voter_lists_total', 2);
        $res->assertJsonCount(12, 'stats.voters_by_month');
        $res->assertJsonStructure([
            'stats' => [
                'voters_by_month' => [
                    ['month', 'count'],
                ],
            ],
        ]);

        // The current month bucket (last element) must hold the 4 voters just created.
        $current = now()->format('Y-m');
        $res->assertJsonPath('stats.voters_by_month.11.month', $current);
        $res->assertJsonPath('stats.voters_by_month.11.count', 4);
    }

    public function test_admin_stats_requires_authorization(): void
    {
        $this->getJson('/api/admin/stats', ['Owner' => fake()->uuid()])
            ->assertStatus(401);
    }
}
