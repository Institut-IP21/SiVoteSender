<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Str;

class VoterListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = 'c1c88f6a-f437-4080-80f0-fe40f596c050';
    }

    /**
     * Create a voterlist via the API and return its ID.
     */
    protected function createVoterList(string $title = 'Test VoterList')
    {
        $response = $this->withHeaders([
            'Authorization' => $this->token,
            'Owner' => $this->owner,
        ])->post('/api/voterlist', ['title' => $title]);

        $response->assertSuccessful();

        return $response->json()['data']['id'];
    }

    /**
     * Add two voters to a voterlist via the API and return the first voter's ID.
     */
    protected function addVotersToList(string $voterlistId): string
    {
        $response = $this->withHeaders([
            'Authorization' => $this->token,
            'Owner' => $this->owner,
        ])->post('/api/voterlist/' . $voterlistId . '/voters', [
            'voters' => json_encode([
                ['title' => 'Test 1', 'email' => 'test1@example.org'],
                ['title' => 'Test 2', 'email' => 'test2@example.org'],
            ]),
        ]);

        $response->assertSuccessful();

        return $response->json()['data']['voters'][0]['id'];
    }

    public function testMissingAuth(): void
    {
        $response = $this
            ->withHeaders([
                'Owner' => $this->owner,
            ])
            ->get('/api/voterlist');

        $response->assertUnauthorized();
    }

    public function testMissingOwner(): void
    {
        $response = $this
            ->withHeaders([
                'Authorization' => $this->token,
            ])
            ->get('/api/voterlist');

        $response->assertUnauthorized();
    }

    public function testCreateVoterList(): void
    {
        $title = "Test VoterList";

        $response = $this
            ->withHeaders([
                'Authorization' => $this->token,
                'Owner' => $this->owner,
            ])
            ->post('/api/voterlist', [
                'title' => $title,
            ]);

        $response->assertSuccessful();
        $response->assertJsonFragment(['title' => $title]);
        $response->assertJsonFragment(['voters' => 0]);
        $response->assertJsonFragment(['owner' => $this->owner]);
    }

    public function testWrongOwner(): void
    {
        $id = $this->createVoterList();

        $response = $this
            ->withHeaders([
                'Authorization' => $this->token,
                'Owner' => "wrong-owner",
            ])
            ->get('/api/voterlist/' . $id);

        $response->assertStatus(403);
    }

    public function testGetVoterList(): void
    {
        $title = "Test VoterList";
        $id = $this->createVoterList($title);

        $response = $this
            ->withHeaders([
                'Authorization' => $this->token,
                'Owner' => $this->owner,
            ])
            ->get('/api/voterlist/' . $id);

        $response->assertSuccessful();
        $response->assertJsonFragment(['title' => $title]);
        $response->assertJsonFragment(['voters' => 0]);
        $response->assertJsonFragment(['owner' => $this->owner]);
    }

    public function testUpdateVoterList(): void
    {
        $id = $this->createVoterList();
        $title = "Test VoterList NEW";

        $response = $this
            ->withHeaders([
                'Authorization' => $this->token,
                'Owner' => $this->owner,
            ])
            ->post('/api/voterlist/' . $id, [
                'title' => $title,
            ]);

        $response->assertSuccessful();
        $response->assertJsonFragment(['title' => $title]);
        $response->assertJsonFragment(['voters' => 0]);
        $response->assertJsonFragment(['owner' => $this->owner]);
    }

    public function testAddVoters(): void
    {
        $id = $this->createVoterList();

        $response = $this
            ->withHeaders([
                'Authorization' => $this->token,
                'Owner' => $this->owner,
            ])
            ->post('/api/voterlist/' . $id . '/voters', [
                'voters' => json_encode([
                    ['title' => 'Test 1', 'email' => 'test1@example.org'],
                    ['title' => 'Test 2', 'email' => 'test2@example.org'],
                ]),
            ]);

        $response->assertSuccessful();
        $response->assertJsonFragment(['title' => 'Test 1']);
        $response->assertJsonFragment(['email' => 'test1@example.org']);
        $response->assertJsonFragment(['title' => 'Test 2']);
        $response->assertJsonFragment(['voters' => 2]);
        $response->assertJsonFragment(['voters_email_verified' => 0]);
        $response->assertJsonFragment(['sentMessages' => 0]);
        $response->assertJsonFragment(['owner' => $this->owner]);
    }

    public function testGetListOfVotersForVoterList(): void
    {
        $voterlistId = $this->createVoterList();
        $voterId = $this->addVotersToList($voterlistId);

        $response = $this
            ->withHeaders([
                'Authorization' => $this->token,
                'Owner' => $this->owner,
            ])
            ->get('/api/voterlist/' . $voterlistId . '/voters?sort_by=id&sort_direction=desc');

        $response->assertSuccessful();
        $response->assertJsonFragment(['id' => (int) $voterId]);
        $response->assertJsonFragment(['email' => 'test1@example.org']);
        $response->assertJsonCount(2, 'data');
    }

    public function testRemoveVoters(): void
    {
        $voterlistId = $this->createVoterList();
        $voterId = $this->addVotersToList($voterlistId);

        $response = $this
            ->withHeaders([
                'Authorization' => $this->token,
                'Owner' => $this->owner,
            ])
            ->delete('/api/voterlist/' . $voterlistId . '/voters', [
                'voters' => json_encode([$voterId]),
            ]);

        $response->assertSuccessful();
        $response->assertJsonMissing(['title' => 'Test 1']);
        $response->assertJsonMissing(['email' => 'test1@example.org']);
        $response->assertJsonFragment(['title' => 'Test 2']);
        $response->assertJsonFragment(['voters' => 1]);
        $response->assertJsonFragment(['voters_email_verified' => 0]);
        $response->assertJsonFragment(['sentMessages' => 0]);
        $response->assertJsonFragment(['owner' => $this->owner]);
    }

    /**
     * Security regression (IDOR): an owner must not be able to view another
     * owner's voter via /api/voter/{voter} — the can:view,voter guard.
     */
    public function testCannotViewVoterOfAnotherOwner(): void
    {
        $voterlistId = $this->createVoterList();
        $voterId = $this->addVotersToList($voterlistId);

        $response = $this
            ->withHeaders([
                'Authorization' => $this->token,
                'Owner' => 'some-other-owner',
            ])
            ->get('/api/voter/' . $voterId);

        $response->assertStatus(403);
    }

    /**
     * Security regression (IDOR): an owner must not be able to delete another
     * owner's voter via /api/voter/{voter} — the can:delete,voter guard.
     */
    public function testCannotDeleteVoterOfAnotherOwner(): void
    {
        $voterlistId = $this->createVoterList();
        $voterId = $this->addVotersToList($voterlistId);

        $response = $this
            ->withHeaders([
                'Authorization' => $this->token,
                'Owner' => 'some-other-owner',
            ])
            ->delete('/api/voter/' . $voterId);

        $response->assertStatus(403);

        // The original owner can still see the voter — it was not deleted.
        $this->withHeaders([
            'Authorization' => $this->token,
            'Owner' => $this->owner,
        ])->get('/api/voter/' . $voterId)->assertSuccessful();
    }

    /**
     * Security regression (IDOR): removeVoters must only ever detach/destroy
     * voters that belong to the targeted list. Passing a foreign owner's voter
     * ID is a no-op for that voter (it stays alive and on its own list).
     */
    public function testRemoveVotersCannotDeleteForeignOwnerVoter(): void
    {
        // Owner A's list + voter (the victim).
        $victimListId = $this->createVoterList('Victim list');
        $victimVoterId = $this->addVotersToList($victimListId);

        // Owner B's own list, into which they will try to smuggle A's voter id.
        $attackerListId = $this->withHeaders([
            'Authorization' => $this->token,
            'Owner' => 'some-other-owner',
        ])->post('/api/voterlist', ['title' => 'Attacker list'])
            ->json()['data']['id'];

        $response = $this
            ->withHeaders([
                'Authorization' => $this->token,
                'Owner' => 'some-other-owner',
            ])
            ->delete('/api/voterlist/' . $attackerListId . '/voters', [
                'voters' => json_encode([$victimVoterId]),
            ]);

        // The call itself is authorized for the attacker's own list, but the
        // foreign voter id must be ignored — not deleted.
        $response->assertSuccessful();

        // Owner A's voter is still there.
        $this->withHeaders([
            'Authorization' => $this->token,
            'Owner' => $this->owner,
        ])->get('/api/voter/' . $victimVoterId)->assertSuccessful();
    }

    public function testGetListOfVoterLists(): void
    {
        $title = "Test VoterList";
        $id = $this->createVoterList($title);

        $response = $this
            ->withHeaders([
                'Authorization' => $this->token,
                'Owner' => $this->owner,
            ])
            ->get('/api/voterlist?sort_by=id&sort_direction=desc');

        $response->assertSuccessful();
        $response->assertJsonFragment(['title' => $title]);
        $response->assertJsonFragment(['id' => $id]);
        $response->assertJsonFragment(['owner' => $this->owner]);
    }

    public function testRemoveVoterList(): void
    {
        $id = $this->createVoterList('TBD');

        $response = $this
            ->withHeaders([
                'Authorization' => $this->token,
                'Owner' => $this->owner,
            ])
            ->delete('/api/voterlist/' . $id);

        $response->assertSuccessful();
    }
}
