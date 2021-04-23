<?php

namespace Tests\Feature;

use App\Mail\BallotInvite;
use App\Models\VoterList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use App\Mail\Verification as MailVerification;
use App\Models\GlobalEmailBlockList;
use App\Models\Voter;

class ExecuteElectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = 'a2c88f6a-f437-4080-80f0-fe40f596c050';
    }

    public function testStartElection()
    {

        $voterlist = VoterList::factory()
            ->has(Voter::factory()->count(4))
            ->create(
                [
                    'owner' => $this->owner
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
            ->post(
                '/api/voterlist/' . $voterlist->id . '/send-invites',
                [
                    'batch' => '5aaa7b5d-41e8-4e40-93b4-acd506403ee4',
                    'codes' =>
                    [
                        'code1',
                        'code2',
                        'code3',
                        'code4',
                    ],
                    'template' => 'Template %%CODE%% and %%LINK%%',
                    'subject' => 'Subject TEST',
                    'url' => 'http://test.lan/%%CODE%%'
                ]
            );

        $response->assertSuccessful();

        Mail::assertQueued(BallotInvite::class, 4);
    }

    public function testStartElectionWithBlockedEmail()
    {

        $voterlist = VoterList::factory()
            ->has(Voter::factory()->count(4))
            ->create(
                [
                    'owner' => $this->owner
                ]
            );

        $blockedVoter = $voterlist->voters->first();

        GlobalEmailBlockList::create([
            'email' => $blockedVoter->email,
            'status' => 'bounce',
        ]);

        Mail::fake();

        $response = $this
            ->withHeaders(
                [
                    'Authorization' => $this->token,
                    'Owner' => $this->owner,
                ]
            )
            ->post(
                '/api/voterlist/' . $voterlist->id . '/send-invites',
                [
                    'batch' => '5aaa7b5d-41e8-4e40-93b4-acd506403ee4',
                    'codes' =>
                    [
                        'code1',
                        'code2',
                        'code3',
                        'code4',
                    ],
                    'template' => 'Template %%CODE%% and %%LINK%%',
                    'subject' => 'Subject TEST',
                    'url' => 'http://test.lan/%%CODE%%'
                ]
            );

        $response->assertStatus(409);
        Mail::assertQueued(BallotInvite::class, 0);
    }
}
