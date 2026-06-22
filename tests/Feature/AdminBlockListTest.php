<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GlobalEmailBlockList;
use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBlockListTest extends TestCase
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
    private function authHeaders(?string $owner = null): array
    {
        return [
            'Authorization' => $this->apiToken,
            'Owner'         => $owner ?? fake()->uuid(),
        ];
    }

    public function test_block_list_returns_rows_newest_first_with_owner_enrichment(): void
    {
        $owner = fake()->uuid();

        // Older block-list row, with a matching voter on a list owned by $owner.
        GlobalEmailBlockList::create([
            'email'      => 'blocked@example.org',
            'status'     => GlobalEmailBlockList::STATUS_BOUNCE,
            'status_msg' => 'mailbox full',
        ]);
        // Newer block-list row with no list membership — owners must be empty.
        GlobalEmailBlockList::create([
            'email'      => 'orphan@example.org',
            'status'     => GlobalEmailBlockList::STATUS_COMPLAINT,
            'status_msg' => 'marked as spam',
        ]);

        // Force a clear created_at ordering (model auto-timestamps would tie on save()).
        GlobalEmailBlockList::query()->where('email', 'blocked@example.org')
            ->update(['created_at' => now()->subDay()]);
        GlobalEmailBlockList::query()->where('email', 'orphan@example.org')
            ->update(['created_at' => now()]);

        $list  = VoterList::factory()->create(['owner' => $owner]);
        $voter = Voter::factory()->create(['email' => 'blocked@example.org']);
        $list->voters()->attach($voter->id);

        $res = $this->getJson('/api/admin/deliverability/block-list', $this->authHeaders());

        $res->assertOk();
        $res->assertJsonCount(2, 'data');
        // Newest first: the orphan (now) precedes the blocked (yesterday).
        $res->assertJsonPath('data.0.email', 'orphan@example.org');
        $res->assertJsonPath('data.0.owners', []);
        $res->assertJsonPath('data.1.email', 'blocked@example.org');
        $res->assertJsonPath('data.1.owners', [$owner]);
        $res->assertJsonPath('data.1.status', GlobalEmailBlockList::STATUS_BOUNCE);
        $res->assertJsonPath('data.1.status_msg', 'mailbox full');
    }

    public function test_remove_block_deletes_row_and_unblocks_voter(): void
    {
        $email = 'unblock@example.org';

        GlobalEmailBlockList::create([
            'email'  => $email,
            'status' => GlobalEmailBlockList::STATUS_BOUNCE,
        ]);
        $voter = Voter::factory()->create(['email' => $email, 'email_blocked' => true]);

        $res = $this->deleteJson(
            '/api/admin/deliverability/block-list',
            ['email' => $email],
            $this->authHeaders()
        );

        $res->assertOk();
        $res->assertJsonPath('removed', 1);
        $this->assertDatabaseMissing('global_email_block_lists', ['email' => $email]);
        $this->assertFalse((bool) $voter->fresh()?->email_blocked);
    }

    public function test_remove_block_rejects_invalid_email(): void
    {
        $res = $this->deleteJson(
            '/api/admin/deliverability/block-list',
            ['email' => 'not-an-email'],
            $this->authHeaders()
        );

        $res->assertStatus(403);
        $res->assertJsonStructure(['error', 'field_errors' => ['email']]);
    }

    public function test_block_list_requires_authorization_token(): void
    {
        $this->getJson('/api/admin/deliverability/block-list', ['Owner' => fake()->uuid()])
            ->assertStatus(401);
    }

    public function test_block_list_requires_owner_header(): void
    {
        $this->getJson('/api/admin/deliverability/block-list', ['Authorization' => $this->apiToken])
            ->assertStatus(401);
    }
}
