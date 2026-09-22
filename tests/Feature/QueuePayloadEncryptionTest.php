<?php

namespace Tests\Feature;

use App\Jobs\SendVoterEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Fixtures\CodedMailable;
use Tests\TestCase;

class QueuePayloadEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function testStoredJobPayloadDoesNotLeakEmailOrCode(): void
    {
        config(['queue.default' => 'database']);

        $email = 'secret-voter@example.org';
        $code  = 'SECRET-CODE-XYZ';

        SendVoterEmail::dispatch($email, new CodedMailable($code));

        $payload = DB::table('jobs')->value('payload');
        $this->assertNotNull($payload, 'job should have been queued to the database');

        $this->assertStringNotContainsString($email, $payload);
        $this->assertStringNotContainsString($code, $payload);
        $this->assertStringNotContainsString('CodedMailable', $payload);
        $this->assertStringContainsString('SendVoterEmail', $payload);
    }

    public function testEncryptedJobStillSendsWhenProcessed(): void
    {
        config(['queue.default' => 'database']);
        Mail::fake();

        SendVoterEmail::dispatch('voter@example.org', new CodedMailable('CODE-1'));

        Artisan::call('queue:work', [
            '--once'            => true,
            '--stop-when-empty' => true,
        ]);

        Mail::assertSent(CodedMailable::class);
        $this->assertSame(0, DB::table('jobs')->count());
    }
}
