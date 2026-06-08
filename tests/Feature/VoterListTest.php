<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Str;

class VoterListTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = 'c1c88f6a-f437-4080-80f0-fe40f596c050';
    }

    private function authHeaders($owner = null): array
    {
        return [
            'Authorization' => $this->token,
            'Owner' => $owner ?? $this->owner,
        ];
    }

    public function testMissingAuth()
    {
        $response = $this
            ->withHeaders(['Owner' => $this->owner])
            ->get('/api/voterlist');

        $response->assertUnauthorized();
    }

    public function testMissingOwner()
    {
        $response = $this
            ->withHeaders(['Authorization' => $this->token])
            ->get('/api/voterlist');

        $response->assertUnauthorized();
    }

    /**
     * Full voter-list CRUD flow. Self-contained (no @depends) so it works
     * under DatabaseTransactions, which rolls back after each test method.
     */
    public function testVoterListCrudFlow()
    {
        // --- create ---
        $title = "Test VoterList";
        $response = $this->withHeaders($this->authHeaders())
            ->post('/api/voterlist', ['title' => $title]);

        $response->assertSuccessful();
        $response->assertJsonFragment(['title' => $title]);
        $response->assertJsonFragment(['voters' => 0]);
        $response->assertJsonFragment(['owner' => $this->owner]);

        $id = $response->json()['data']['id'];

        // --- wrong owner cannot view it ---
        $this->withHeaders($this->authHeaders('wrong-owner'))
            ->get('/api/voterlist/' . $id)
            ->assertStatus(403);

        // --- get ---
        $response = $this->withHeaders($this->authHeaders())
            ->get('/api/voterlist/' . $id);

        $response->assertSuccessful();
        $response->assertJsonFragment(['title' => $title]);
        $response->assertJsonFragment(['voters' => 0]);
        $response->assertJsonFragment(['owner' => $this->owner]);

        // --- update ---
        $title = "Test VoterList NEW";
        $response = $this->withHeaders($this->authHeaders())
            ->post('/api/voterlist/' . $id, ['title' => $title]);

        $response->assertSuccessful();
        $response->assertJsonFragment(['title' => $title]);
        $response->assertJsonFragment(['voters' => 0]);
        $response->assertJsonFragment(['owner' => $this->owner]);

        // --- add voters ---
        $response = $this->withHeaders($this->authHeaders())
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

        $voterId = $response->json()['data']['voters'][0]['id'];

        // --- list voters for the list ---
        $response = $this->withHeaders($this->authHeaders())
            ->get('/api/voterlist/' . $id . '/voters?sort_by=id&sort_direction=desc');

        $response->assertSuccessful();
        $response->assertJsonFragment(['id' => $voterId]);
        $response->assertJsonFragment(['email' => 'test1@example.org']);
        $response->assertJsonCount(2, 'data');

        // --- remove one voter ---
        $response = $this->withHeaders($this->authHeaders())
            ->delete('/api/voterlist/' . $id . '/voters', [
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

        // --- the list shows up in the index ---
        $response = $this->withHeaders($this->authHeaders())
            ->get('/api/voterlist?sort_by=id&sort_direction=desc');

        $response->assertSuccessful();
        $response->assertJsonFragment(['title' => $title]);
        $response->assertJsonFragment(['id' => $id]);
        $response->assertJsonFragment(['owner' => $this->owner]);
    }

    public function testRemoveVoterList()
    {
        $response = $this->withHeaders($this->authHeaders())
            ->post('/api/voterlist', ['title' => 'TBD']);

        $response->assertSuccessful();
        $id = $response->json()['data']['id'];

        $response = $this->withHeaders($this->authHeaders())
            ->delete('/api/voterlist/' . $id);

        $response->assertSuccessful();
    }
}
