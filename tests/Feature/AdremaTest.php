<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Str;

class AdremaTest extends TestCase
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
                '/api/adrema',
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
                '/api/adrema',
            );

        $response->assertUnauthorized();
    }

    public function testCreateAdrema()
    {
        $title = "Test Adrema";

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->post(
                '/api/adrema',
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
     * @depends testCreateAdrema
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
                '/api/adrema/' . $id,
            );

        $response->assertStatus(403);
    }

    /**
     * @depends testCreateAdrema
     */
    public function testGetAdrema($id)
    {
        $title = "Test Adrema";

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->get(
                '/api/adrema/' . $id,
            );

        $response->assertSuccessful();
        $response->assertJsonFragment(['title' => $title]);
        $response->assertJsonFragment(['voters' => 0]);
        $response->assertJsonFragment(['owner' => $this->owner]);

        return $id;
    }

    /**
     * @depends testGetAdrema
     */
    public function testUpdateAdrema($id)
    {
        $title = "Test Adrema NEW";

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->post(
                '/api/adrema/' . $id,
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
     * @depends testUpdateAdrema
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
                '/api/adrema/' . $id . '/voters',
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
    public function testGetListOfVotersForAdrema($data)
    {
        $voterId = $data[1];
        $adremaId = $data[0];

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->get(
                '/api/adrema/' . $adremaId . '/voters?sort_by=id&sort_direction=desc'
            );

        $response->assertSuccessful();
        $response->assertJsonFragment(['id' => $voterId]);
        $response->assertJsonFragment(['email' => 'test1@example.org']);
        $response->assertJsonCount(2, $key = 'data');

        return $data;
    }

    /**
     * @depends testGetListOfVotersForAdrema
     */
    public function testRemoveVoters($data)
    {
        $voterId = $data[1];
        $adremaId = $data[0];

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->delete(
                '/api/adrema/' . $adremaId . '/voters',
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
     * @depends testUpdateAdrema
     */
    public function testGetListOfAdremas($id)
    {
        $title = "Test Adrema NEW";

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->get(
                '/api/adrema?sort_by=id&sort_direction=desc',
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
    public function testRemoveAdrema()
    {
        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->post(
                '/api/adrema',
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
                '/api/adrema/' . $id,
            );

        $response->assertSuccessful();
    }
}
