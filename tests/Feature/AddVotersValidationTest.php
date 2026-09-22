<?php

namespace Tests\Feature;

use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddVotersValidationTest extends TestCase
{
    use RefreshDatabase;

    private string $owner = 'c1c88f6a-f437-4080-80f0-fe40f596c050';

    private function createVoterList(): string
    {
        return $this->withHeaders([
            'Authorization' => $this->token,
            'Owner'         => $this->owner,
        ])->postJson('/api/voterlist', ['title' => 'List'])->json('data.id');
    }

    private function addVoters(string $listId, array $voters): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders([
            'Authorization' => $this->token,
            'Owner'         => $this->owner,
        ])->postJson('/api/voterlist/' . $listId . '/voters', [
            'voters' => json_encode($voters),
        ]);
    }

    public function testInvalidEmailRejected(): void
    {
        $listId = $this->createVoterList();

        $this->addVoters($listId, [
            ['title' => 'Valid Name', 'email' => 'not-an-email'],
        ])->assertStatus(422);

        $this->assertSame(0, Voter::count());
    }

    public function testCrlfInNameRejected(): void
    {
        $listId = $this->createVoterList();

        $this->addVoters($listId, [
            ['title' => "Evil\r\nBcc: x@y.z", 'email' => 'ok@example.org'],
        ])->assertStatus(422);

        $this->assertSame(0, Voter::count());
    }

    public function testEmptyVoterRejected(): void
    {
        $listId = $this->createVoterList();

        $this->addVoters($listId, [
            ['title' => '', 'email' => 'ok@example.org'],
        ])->assertStatus(422);

        $this->addVoters($listId, [])->assertStatus(422);

        $this->assertSame(0, Voter::count());
    }

    public function testOverlongValueRejected(): void
    {
        $listId = $this->createVoterList();

        $this->addVoters($listId, [
            ['title' => str_repeat('a', 300), 'email' => 'ok@example.org'],
        ])->assertStatus(422);

        $this->assertSame(0, Voter::count());
    }

    public function testValidBatchStored(): void
    {
        $listId = $this->createVoterList();

        $this->addVoters($listId, [
            ['title' => 'Alice', 'email' => 'alice@example.org'],
            ['title' => 'Bob', 'email' => null],
            ['title' => 'Carol'],
        ])->assertOk();

        $this->assertSame(3, Voter::count());
        $this->assertDatabaseHas('voters', ['title' => 'Alice', 'email' => 'alice@example.org']);
    }
}
