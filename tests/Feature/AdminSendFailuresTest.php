<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EmailSendFailure;
use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSendFailuresTest extends TestCase
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

    public function test_send_failures_returns_rows_newest_first_with_fields(): void
    {
        EmailSendFailure::create([
            'recipient' => 'old@example.org',
            'mailable'  => 'App\\Mail\\BallotInvite',
            'error'     => 'Throttling: Maximum sending rate exceeded',
            'attempts'  => 5,
        ]);
        EmailSendFailure::create([
            'recipient' => 'new@example.org',
            'mailable'  => 'App\\Mail\\BallotResult',
            'error'     => 'Connection timeout',
            'attempts'  => 8,
        ]);

        // Force a clear created_at ordering (model auto-timestamps would tie on save()).
        EmailSendFailure::query()->where('recipient', 'old@example.org')
            ->update(['created_at' => now()->subHour()]);
        EmailSendFailure::query()->where('recipient', 'new@example.org')
            ->update(['created_at' => now()]);

        $res = $this->getJson('/api/admin/deliverability/send-failures', $this->authHeaders());

        $res->assertOk();
        $res->assertJsonCount(2, 'data');
        $res->assertJsonStructure([
            'data' => [
                ['id', 'recipient', 'mailable', 'error', 'attempts', 'created_at', 'owners'],
            ],
        ]);
        $res->assertJsonPath('data.0.recipient', 'new@example.org');
        $res->assertJsonPath('data.0.mailable', 'App\\Mail\\BallotResult');
        $res->assertJsonPath('data.0.attempts', 8);
        $res->assertJsonPath('data.1.recipient', 'old@example.org');
    }

    public function test_owners_derived_from_recipient_email_when_voter_list_matches(): void
    {
        $owner = fake()->uuid();
        $list  = VoterList::factory()->create(['owner' => $owner]);
        $voter = Voter::factory()->create(['email' => 'member@example.org']);
        $list->voters()->attach($voter->id);

        EmailSendFailure::create([
            'recipient' => 'member@example.org',
            'mailable'  => 'App\\Mail\\BallotInvite',
            'error'     => 'Throttling',
            'attempts'  => 8,
        ]);

        $res = $this->getJson('/api/admin/deliverability/send-failures', $this->authHeaders());

        $res->assertOk();
        $res->assertJsonPath('data.0.owners', [$owner]);
    }

    public function test_owners_empty_when_no_matching_voter_or_list(): void
    {
        EmailSendFailure::create([
            'recipient' => 'stranger@example.org',
            'mailable'  => 'App\\Mail\\BallotInvite',
            'error'     => 'Throttling',
            'attempts'  => 8,
        ]);

        $res = $this->getJson('/api/admin/deliverability/send-failures', $this->authHeaders());

        $res->assertOk();
        $res->assertJsonPath('data.0.owners', []);
    }

    public function test_send_failures_requires_authorization(): void
    {
        $this->getJson('/api/admin/deliverability/send-failures', ['Owner' => fake()->uuid()])
            ->assertStatus(401);
    }
}
