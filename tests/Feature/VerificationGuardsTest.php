<?php

namespace Tests\Feature;

use App\Mail\Verification as MailVerification;
use App\Models\SentMessage;
use App\Models\Verification;
use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VerificationGuardsTest extends TestCase
{
    use RefreshDatabase;

    private string $owner = 'a2c88f6a-f437-4080-80f0-fe40f596c050';

    private function headers(): array
    {
        return ['Authorization' => $this->token, 'Owner' => $this->owner];
    }

    private function verification(int $voters = 3): Verification
    {
        $list = VoterList::factory()->has(Voter::factory()->count($voters))->create(['owner' => $this->owner]);

        return Verification::factory()->create(['voterlist_id' => $list->id]);
    }

    public function test_start_twice_is_refused_with_409_and_sends_nothing_more(): void
    {
        Mail::fake();
        $verification = $this->verification(3);

        $this->withHeaders($this->headers())->get('/api/verification/' . $verification->id . '/start')->assertOk();
        $this->assertNotNull($verification->refresh()->sent_at);
        $sent = SentMessage::count();

        $this->withHeaders($this->headers())->get('/api/verification/' . $verification->id . '/start')->assertStatus(409);

        $this->assertSame($sent, SentMessage::count());
        Mail::assertSent(MailVerification::class, 3);
    }

    public function test_start_on_an_already_sent_verification_is_refused(): void
    {
        Mail::fake();
        $verification = $this->verification(2);
        $verification->forceFill(['sent_at' => now()->subDay()])->save();

        $this->withHeaders($this->headers())->get('/api/verification/' . $verification->id . '/start')->assertStatus(409);

        Mail::assertNothingSent();
    }

    private function testSend(Verification $verification, mixed $to): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->headers())
            ->postJson('/api/verification/' . $verification->id . '/start/test', ['to' => $to]);
    }

    public function test_test_send_rejects_invalid_addresses_with_422(): void
    {
        Mail::fake();
        $verification = $this->verification(1);

        $this->testSend($verification, json_encode(['ok@example.org', 'not-an-email']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('to.1');

        Mail::assertNothingSent();
    }

    public function test_test_send_caps_recipients_at_five(): void
    {
        Mail::fake();
        $verification = $this->verification(1);
        $six = array_map(fn ($i) => "t{$i}@example.org", range(1, 6));

        $this->testSend($verification, json_encode($six))->assertStatus(422)->assertJsonValidationErrors('to');

        Mail::assertNothingSent();
    }

    public function test_test_send_rejects_empty_and_malformed_payloads(): void
    {
        Mail::fake();
        $verification = $this->verification(1);

        $this->testSend($verification, json_encode([]))->assertStatus(422);
        $this->testSend($verification, 'not json')->assertStatus(422);
        $this->testSend($verification, json_encode('single@example.org'))->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_test_send_accepts_json_string_and_plain_array(): void
    {
        Mail::fake();
        $verification = $this->verification(1);

        $this->testSend($verification, json_encode(['a@example.org', 'b@example.org']))->assertOk();
        $this->testSend($verification, ['c@example.org'])->assertOk();

        Mail::assertSent(MailVerification::class, 3);
    }
}
