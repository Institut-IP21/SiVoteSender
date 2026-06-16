<?php

namespace Tests\Feature\Contract;

use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class InviteContractTest extends TestCase
{
    use RefreshDatabase;

    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = fake()->uuid();

        // Don't depend on the mailer transport: the send path dispatches
        // SendVoterEmail (run inline on the sync queue), which would otherwise
        // attempt a real SMTP delivery.
        Mail::fake();
    }

    private function authHeaders(): array
    {
        return ['Authorization' => $this->token, 'Owner' => $this->owner];
    }

    private function createVoterListWithVoters(int $voterCount = 3): VoterList
    {
        $voterList = VoterList::factory()->create(['owner' => $this->owner]);
        $voters = Voter::factory()->count($voterCount)->create();
        $voterList->voters()->attach($voters->pluck('id'));

        return $voterList;
    }

    public function test_send_invites_accepts_flat_code_array(): void
    {
        $voterList = $this->createVoterListWithVoters(3);

        // Flat array of UUIDs -- matches engine's generateCodes output for secret ballots
        $codes = [
            Str::uuid()->toString(),
            Str::uuid()->toString(),
            Str::uuid()->toString(),
        ];

        $response = $this->post(
            "/api/voterlist/{$voterList->id}/send-invites",
            [
                'batch' => Str::uuid()->toString(),
                'codes' => $codes,
                'template' => 'Your voting code is %%CODE%%',
                'subject' => 'You are invited to vote',
                'url' => 'http://engine.test/election/123/ballot/456?code=%%CODE%%',
                'owner' => $this->owner,
            ],
            $this->authHeaders()
        );

        $response->assertSuccessful();
        $response->assertJson(['success' => true]);
    }

    public function test_send_invites_requires_all_params(): void
    {
        $voterList = $this->createVoterListWithVoters(1);

        // Missing required fields
        $response = $this->post(
            "/api/voterlist/{$voterList->id}/send-invites",
            ['owner' => $this->owner],
            $this->authHeaders()
        );

        // Sender returns 403 for validation errors
        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
        $this->assertNotEmpty($response->json('field_errors'));
    }

    public function test_send_session_invites_accepts_structured_codes(): void
    {
        $voterList = $this->createVoterListWithVoters(2);

        // Structured codes -- matches engine's generatePublicCodes output
        $codes = [
            [
                'code' => Str::uuid()->toString(),
                'voter' => 'alice@example.com',
                'access_url' => 'http://engine.test/signed-url-1',
            ],
            [
                'code' => Str::uuid()->toString(),
                'voter' => 'bob@example.com',
                'access_url' => 'http://engine.test/signed-url-2',
            ],
        ];

        $response = $this->post(
            "/api/voterlist/{$voterList->id}/send-session-invites",
            [
                'batch' => Str::uuid()->toString(),
                'codes' => $codes,
                'template' => 'Your session voting link is ready',
                'subject' => 'Session vote',
                'owner' => $this->owner,
            ],
            $this->authHeaders()
        );

        $response->assertSuccessful();
        $response->assertJson(['success' => true]);
    }

    public function test_send_results_accepts_csv_and_result_link(): void
    {
        $voterList = $this->createVoterListWithVoters(2);

        $response = $this->post(
            "/api/voterlist/{$voterList->id}/send-results",
            [
                'batch' => Str::uuid()->toString(),
                'template' => 'Results are attached.',
                'subject' => 'Voting results for My Ballot',
                'csv' => "component,option,votes\nQ1,Yes,10\nQ1,No,5",
                'resultLink' => 'http://engine.test/election/123/ballot/456/result',
                'owner' => $this->owner,
            ],
            $this->authHeaders()
        );

        $response->assertSuccessful();
        $response->assertJson(['success' => true]);
    }
}
