<?php

namespace Tests\Feature;

use App\Models\GlobalEmailBlockList;
use App\Models\SentMessage;
use App\Models\Voter;
use App\Models\VoterList;
use Aws\Sns\MessageValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SnsWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $ownedArn = 'arn:aws:sns:eu-central-1:111111111111:sivote-ses';
    private string $foreignArn = 'arn:aws:sns:us-east-1:999999999999:attacker-topic';

    /** @param array<string, mixed> $message */
    private function postSns(array $message): \Illuminate\Testing\TestResponse
    {
        return $this->call(
            'POST',
            '/api/sns/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($message),
        );
    }

    private function seedVoter(string $email): Voter
    {
        $list = VoterList::factory()->create(['owner' => 'c1c88f6a-f437-4080-80f0-fe40f596c050']);
        $voter = Voter::factory()->create(['email' => $email, 'email_blocked' => false]);
        $list->voters()->attach($voter);

        SentMessage::factory()->create([
            'voter_id'     => $voter->id,
            'voterlist_id' => $list->id,
            'batch_uuid'   => (string) Str::uuid(),
            'type'         => SentMessage::TYPE_EMAIL,
            'status'       => SentMessage::STATUS_SENT,
            'successful'   => false,
        ]);

        return $voter;
    }

    /**
     * Signed with a throwaway cert; binds a MessageValidator that trusts it so no AWS cert fetch happens.
     *
     * @return array<string, mixed>
     */
    private function signedBounce(string $topicArn, string $victimEmail): array
    {
        $inner = json_encode([
            'notificationType' => 'Bounce',
            'bounce' => [
                'bounceType'    => 'Permanent',
                'bounceSubType' => 'General',
                'bouncedRecipients' => [['diagnosticCode' => 'smtp; 550 user unknown']],
            ],
            'mail' => ['destination' => [$victimEmail]],
        ]);

        $message = [
            'Type'      => 'Notification',
            'MessageId' => (string) Str::uuid(),
            'TopicArn'  => $topicArn,
            'Message'   => $inner,
            'Timestamp' => now()->toIso8601String(),
        ];

        $keypair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($keypair, $privateKeyPem);
        $csr  = openssl_csr_new(['commonName' => 'sns.amazonaws.com'], $keypair);
        $x509 = openssl_csr_sign($csr, null, $keypair, 365);
        openssl_x509_export($x509, $certPem);

        $stringToSign = '';
        foreach (['Message', 'MessageId', 'Subject', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'] as $key) {
            if (isset($message[$key])) {
                $stringToSign .= "{$key}\n{$message[$key]}\n";
            }
        }

        openssl_sign($stringToSign, $rawSig, $privateKeyPem, OPENSSL_ALGO_SHA1);

        $message['SignatureVersion'] = '1';
        $message['Signature']        = base64_encode($rawSig);
        $message['SigningCertURL']   = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-test.pem';

        $this->app->bind(MessageValidator::class, fn () => new MessageValidator(fn ($url) => $certPem));

        return $message;
    }

    public function testEmptyAllowlistIsRejected(): void
    {
        config(['services.sns.topic_arns' => []]);
        $voter = $this->seedVoter('victim@example.org');

        $this->postSns($this->signedBounce($this->ownedArn, 'victim@example.org'))
            ->assertStatus(403);

        $this->assertFalse((bool) $voter->refresh()->email_blocked);
        $this->assertSame(0, GlobalEmailBlockList::count());
    }

    public function testNonAllowlistedTopicIsRejected(): void
    {
        config(['services.sns.topic_arns' => [$this->ownedArn]]);
        $voter = $this->seedVoter('victim@example.org');

        $this->postSns($this->signedBounce($this->foreignArn, 'victim@example.org'))
            ->assertStatus(403);

        $this->assertFalse((bool) $voter->refresh()->email_blocked);
        $this->assertSame(0, GlobalEmailBlockList::count());
    }

    public function testForgedBounceForForeignTopicDoesNotBlockVoter(): void
    {
        config(['services.sns.topic_arns' => [$this->ownedArn]]);
        $voter = $this->seedVoter('target@example.org');

        $this->postSns($this->signedBounce($this->foreignArn, 'target@example.org'))
            ->assertStatus(403);

        $this->assertFalse((bool) $voter->refresh()->email_blocked);
        $this->assertDatabaseMissing('global_email_block_lists', ['email' => 'target@example.org']);
    }

    public function testAllowlistedTopicWithValidSignatureIsAccepted(): void
    {
        config(['services.sns.topic_arns' => [$this->ownedArn]]);
        $voter = $this->seedVoter('real@example.org');

        $this->postSns($this->signedBounce($this->ownedArn, 'real@example.org'))
            ->assertOk();

        $this->assertTrue((bool) $voter->refresh()->email_blocked);
        $this->assertDatabaseHas('global_email_block_lists', ['email' => 'real@example.org']);
    }
}
