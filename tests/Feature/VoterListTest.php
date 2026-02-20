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

    public function testMissingAuth()
    {
        $response = $this
            ->withHeaders([
                'Owner' => $this->owner,
            ])
            ->get('/api/voterlist');

        $response->assertUnauthorized();
    }

    public function testMissingOwner()
    {
        $response = $this
            ->withHeaders([
                'Authorization' => $this->token,
            ])
            ->get('/api/voterlist');

        $response->assertUnauthorized();
    }

    public function testCreateVoterList()
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

    public function testWrongOwner()
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

    public function testGetVoterList()
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

    public function testUpdateVoterList()
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

    public function testAddVoters()
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

    public function testGetListOfVotersForVoterList()
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

    public function testRemoveVoters()
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

    public function testGetListOfVoterLists()
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

    public function testRemoveVoterList()
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
