<?php

namespace Tests\Feature;

use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /api/voter/{voter} — update a single voter's identity fields. Ownership is
 * enforced by can:update,voter; changing the email resets the verification/block state.
 */
class VoterUpdateTest extends TestCase
{
    use RefreshDatabase;

    private string $owner = 'c1c88f6a-f437-4080-80f0-fe40f596c050';

    private function makeVoter(string $owner, array $attrs = []): Voter
    {
        $voterlist = \App\Models\VoterList::create(['title' => 'L', 'owner' => $owner]);
        $voter = Voter::create(array_merge(['title' => 'Old', 'email' => 'old@example.org'], $attrs));
        $voterlist->voters()->attach($voter->id);

        return $voter;
    }

    public function test_updates_identity_fields(): void
    {
        $voter = $this->makeVoter($this->owner);

        $res = $this->withHeaders(['Authorization' => $this->token, 'Owner' => $this->owner])
            ->post('/api/voter/' . $voter->id, ['title' => 'New Name', 'phone' => '+38640']);

        $res->assertOk();
        $voter->refresh();
        $this->assertSame('New Name', $voter->title);
        $this->assertSame('+38640', $voter->phone);
        $this->assertSame('old@example.org', $voter->email); // untouched key preserved
    }

    public function test_changing_email_resets_verification_and_block_state(): void
    {
        $voter = $this->makeVoter($this->owner, [
            'email' => 'old@example.org',
            'email_verified' => now(),
            'email_blocked' => true,
        ]);

        $res = $this->withHeaders(['Authorization' => $this->token, 'Owner' => $this->owner])
            ->post('/api/voter/' . $voter->id, ['email' => 'new@example.org']);

        $res->assertOk();
        $voter->refresh();
        $this->assertSame('new@example.org', $voter->email);
        $this->assertNull($voter->email_verified);
        $this->assertFalse((bool) $voter->email_blocked);
    }

    public function test_a_bad_email_is_rejected(): void
    {
        $voter = $this->makeVoter($this->owner);

        $this->withHeaders(['Authorization' => $this->token, 'Owner' => $this->owner])
            ->post('/api/voter/' . $voter->id, ['email' => 'not-an-email'])
            ->assertStatus(403); // findErrors() returns a 403 "Request invalid" envelope
    }

    public function test_another_owner_cannot_update(): void
    {
        $voter = $this->makeVoter($this->owner);

        $this->withHeaders(['Authorization' => $this->token, 'Owner' => 'a0000000-0000-4000-8000-000000000000'])
            ->post('/api/voter/' . $voter->id, ['title' => 'Hijack'])
            ->assertStatus(403);

        $this->assertSame('Old', $voter->refresh()->title);
    }
}
