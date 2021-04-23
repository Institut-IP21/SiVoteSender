<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Str;

class VoterListTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = 'c1c88f6a-f437-4080-80f0-fe40f596c050';
    }

    public function testMissingAuth()
    {
        $response = $this
            ->withHeaders(
                [
                    'Owner' => $this->owner,
                ]
            )
            ->get(
                '/api/voterlist',
            );

        $response->assertUnauthorized();
    }

    public function testMissingOwner()
    {
        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                ]
            )
            ->get(
                '/api/voterlist',
            );

        $response->assertUnauthorized();
    }

    public function testCreateVoterList()
    {
        $title = "Test VoterList";

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->post(
                '/api/voterlist',
                [
                    'title' => $title,
                ]
            );

        $response->assertSuccessful();
        $response->assertJsonFragment(['title' => $title]);
        $response->assertJsonFragment(['voters' => 0]);
        $response->assertJsonFragment(['owner' => $this->owner]);

        $data = $response->json();

        return $data['data']['id'];
    }

    /**
     * @depends testCreateVoterList
     */
    public function testWrongOwner($id)
    {
        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => "wrong-owner",
                ]
            )
            ->get(
                '/api/voterlist/' . $id,
            );

        $response->assertStatus(403);
    }

    /**
     * @depends testCreateVoterList
     */
    public function testGetVoterList($id)
    {
        $title = "Test VoterList";

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->get(
                '/api/voterlist/' . $id,
            );

        $response->assertSuccessful();
        $response->assertJsonFragment(['title' => $title]);
        $response->assertJsonFragment(['voters' => 0]);
        $response->assertJsonFragment(['owner' => $this->owner]);

        return $id;
    }

    /**
     * @depends testGetVoterList
     */
    public function testUpdateVoterList($id)
    {
        $title = "Test VoterList NEW";

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->post(
                '/api/voterlist/' . $id,
                [
                    'title' => $title
                ]
            );

        $response->assertSuccessful();
        $response->assertJsonFragment(['title' => $title]);
        $response->assertJsonFragment(['voters' => 0]);
        $response->assertJsonFragment(['owner' => $this->owner]);

        return $id;
    }

    /**
     * @depends testUpdateVoterList
     */
    public function testAddVoters($id)
    {
        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->post(
                '/api/voterlist/' . $id . '/voters',
                [
                    'voters' => json_encode([
                        [
                            'title' => 'Test 1',
                            'email' => 'test1@example.org'
                        ],
                        [
                            'title' => 'Test 2',
                            'email' => 'test2@example.org'
                        ]
                    ])
                ]
            );

        $response->assertSuccessful();
        $response->assertJsonFragment(['title' => 'Test 1']);
        $response->assertJsonFragment(['email' => 'test1@example.org']);
        $response->assertJsonFragment(['title' => 'Test 2']);
        $response->assertJsonFragment(['voters' => 2]);
        $response->assertJsonFragment(['voters_email_verified' => 0]);
        $response->assertJsonFragment(['sentMessages' => 0]);
        $response->assertJsonFragment(['owner' => $this->owner]);

        $data = $response->json();

        return [$id, $data['data']['voters'][0]['id']];
    }

    /**
     * @depends testAddVoters
     */
    public function testGetListOfVotersForVoterList($data)
    {
        $voterId = $data[1];
        $voterlistId = $data[0];

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->get(
                '/api/voterlist/' . $voterlistId . '/voters?sort_by=id&sort_direction=desc'
            );

        $response->assertSuccessful();
        $response->assertJsonFragment(['id' => $voterId]);
        $response->assertJsonFragment(['email' => 'test1@example.org']);
        $response->assertJsonCount(2, $key = 'data');

        return $data;
    }

    /**
     * @depends testGetListOfVotersForVoterList
     */
    public function testRemoveVoters($data)
    {
        $voterId = $data[1];
        $voterlistId = $data[0];

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->delete(
                '/api/voterlist/' . $voterlistId . '/voters',
                [
                    'voters' => json_encode(
                        [
                            $voterId
                        ]
                    )
                ]
            );

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
     * @depends testUpdateVoterList
     */
    public function testGetListOfVoterLists($id)
    {
        $title = "Test VoterList NEW";

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->get(
                '/api/voterlist?sort_by=id&sort_direction=desc',
            );

        $response->assertSuccessful();
        $response->assertJsonFragment(['title' => $title]);
        $response->assertJsonFragment(['id' => $id]);
        $response->assertJsonFragment(['owner' => $this->owner]);

        return $id;
    }

    /**
     * @depends testAddVoters
     */
    public function testRemoveVoterList()
    {
        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->post(
                '/api/voterlist',
                [
                    'title' => 'TBD',
                ]
            );

        $response->assertSuccessful();
        $id = $response->json()['data']['id'];

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->delete(
                '/api/voterlist/' . $id,
            );

        $response->assertSuccessful();
    }
}
