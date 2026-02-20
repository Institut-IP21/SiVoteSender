<?php

namespace Tests\Feature\Contract;

use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoterListContractTest extends TestCase
{
    use RefreshDatabase;

    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = fake()->uuid();
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

    public function test_get_voterlist_voters_have_email_field(): void
    {
        $voterList = $this->createVoterListWithVoters(3);

        $response = $this->get(
            "/api/voterlist/{$voterList->id}",
            $this->authHeaders()
        );

        $response->assertSuccessful();

        // web_app does: array_column($voterList['voters'], 'email')
        $voters = $response->json('data.voters');
        $this->assertIsArray($voters);
        $this->assertCount(3, $voters);

        foreach ($voters as $voter) {
            $this->assertArrayHasKey('email', $voter);
            $this->assertNotEmpty($voter['email']);
        }

        // Verify array_column works as web_app uses it
        $emails = array_column($voters, 'email');
        $this->assertCount(3, $emails);
    }

    public function test_get_voterlist_has_stats_voters_count(): void
    {
        $voterList = $this->createVoterListWithVoters(5);

        $response = $this->get(
            "/api/voterlist/{$voterList->id}",
            $this->authHeaders()
        );

        $response->assertSuccessful();

        // BallotBuilder checks: $voterList['stats']['voters'] > 0
        $stats = $response->json('data.stats');
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('voters', $stats);
        $this->assertEquals(5, $stats['voters']);
    }

    public function test_get_voterlist_has_id_field(): void
    {
        $voterList = $this->createVoterListWithVoters(1);

        $response = $this->get(
            "/api/voterlist/{$voterList->id}",
            $this->authHeaders()
        );

        $response->assertSuccessful();

        // web_app uses $voterlist['id'] in sendInvites call
        $id = $response->json('data.id');
        $this->assertNotNull($id);
        $this->assertEquals($voterList->id, $id);
    }
}
