<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use App\Mail\Verification as MailVerification;
use App\Models\Adrema;
use App\Models\GlobalEmailBlockList;
use App\Models\Verification;
use App\Models\Voter;

class VerificationTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = 'a2c88f6a-f437-4080-80f0-fe40f596c050';
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
                '/api/verification',
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
                '/api/verification',
            );

        $response->assertUnauthorized();
    }

    //
    //
    //

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
    public function testCreateVerification($adremaId)
    {
        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->post(
                '/api/verification',
                [
                    'adrema_id' => $adremaId,
                    'template'  => "Template",
                    'subject'   => "Subject",
                ]
            );

        $response->assertSuccessful();
        $response->assertJsonFragment(['template' => "Template"]);
        $response->assertJsonFragment(['sentMessages' => 0]);
        $response->assertJsonFragment(['owner' => $this->owner]);
        $response->assertJsonFragment(['sent_at' => null]);
        $response->assertJsonFragment(['redirect_url' => null]);

        $data = $response->json();

        return [$adremaId, $data['data']['id']];
    }

    /**
     * @depends testCreateVerification
     */
    public function testUpdateVerification($data)
    {
        list($adremaId, $verificationId) = $data;

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->post(
                '/api/verification/' . $verificationId,
                [
                    'template'  => "Template 2",
                    'subject'   => null,
                ]
            );

        $response->assertSuccessful();
        $response->assertJsonFragment(['template' => "Template 2"]);
        $response->assertJsonFragment(['subject' => null]);
        $response->assertJsonFragment(['sentMessages' => 0]);
        $response->assertJsonFragment(['owner' => $this->owner]);
        $response->assertJsonFragment(['sent_at' => null]);
        $response->assertJsonFragment(['redirect_url' => null]);

        return [$adremaId, $verificationId];
    }

    /**
     * @depends testUpdateVerification
     */
    public function testTestStartVerification($data)
    {
        list($adremaId, $verificationId) = $data;

        // Resetting values
        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->post(
                '/api/verification/' . $verificationId,
                [
                    'template'  => "Template",
                    'subject'   => "Subject",
                ]
            );

        $response->assertSuccessful();

        Mail::fake();

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->post(
                '/api/verification/' . $verificationId . '/start/test',
                [
                    'to'  => json_encode(
                        [
                            'admin1@example.org',
                            'admin2@example.org'
                        ]
                    ),
                ]
            );

        $response->assertSuccessful();

        Mail::assertQueued(MailVerification::class, 2);

        Mail::assertQueued(
            MailVerification::class,
            function ($mail) {
                return $mail->hasTo('admin1@example.org')
                    || $mail->hasTo('admin2@example.org');
            }
        );

        Mail::assertQueued(
            MailVerification::class,
            function ($mail) {
                return $mail->subject == 'Subject'
                    && $mail->template == 'Template';
            }
        );


        return [$adremaId, $verificationId];
    }


    public function testRealStartVerification()
    {

        $adrema = Adrema::factory()
            ->has(Voter::factory()->count(15))
            ->create(
                [
                    'owner' => $this->owner
                ]
            );

        $verification = Verification::factory()->create(
            [
                'adrema_id' => $adrema->id,
            ]
        );

        Mail::fake();

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->get(
                '/api/verification/' . $verification->id . '/start'
            );

        $response->assertSuccessful();

        Mail::assertQueued(MailVerification::class, 15);

        $link = null;
        $email = null;
        Mail::assertQueued(
            MailVerification::class,
            function ($mail) use (&$link, &$email) {
                $link = $mail->url;
                $email = $mail->to[0]['address'];
                return (bool) $mail->url;
            }
        );

        $response = $this->get($link);

        $voter = Voter::where('email', $email)->first();
        $this->assertNotNull($voter->email_verified);
    }

    public function testRealStartVerificationWithBlockedVoters()
    {

        $adrema = Adrema::factory()
            ->has(Voter::factory()->count(5))
            ->create(
                [
                    'owner' => $this->owner
                ]
            );

        $blockedVoter = $adrema->voters->first();

        GlobalEmailBlockList::create([
            'email' => $blockedVoter->email,
            'status' => 'bounce',
        ]);

        $verification = Verification::factory()->create(
            [
                'adrema_id' => $adrema->id,
            ]
        );

        Mail::fake();

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->get(
                '/api/verification/' . $verification->id . '/start'
            );

        $response->assertSuccessful();

        Mail::assertQueued(MailVerification::class, 4);
    }

    public function testRealStartForSingleVoterVerification()
    {

        $adrema = Adrema::factory()
            ->has(Voter::factory()->count(1))
            ->create(
                [
                    'owner' => $this->owner
                ]
            );

        $voter = $adrema->voters->first();

        Mail::fake();

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->get(
                '/api/verification/single/' . $voter->id . '/start'
            );

        $response->assertSuccessful();

        Mail::assertQueued(MailVerification::class, 1);

        $link = null;
        Mail::assertQueued(
            MailVerification::class,
            function ($mail) use (&$link, &$email) {
                $link = $mail->url;
                return (bool) $mail->url;
            }
        );

        $voter = $voter->fresh();
        $this->assertNull($voter->email_verified);
        $response = $this->get($link);

        $voter = $voter->fresh();
        $this->assertNotNull($voter->email_verified);
    }
}
