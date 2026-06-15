<?php

namespace Tests\Feature;

use App\Models\GlobalEmailBlockList;
use App\Services\Sender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Fixtures\PlainMailable;
use Tests\TestCase;

class SenderTest extends TestCase
{
    use RefreshDatabase;

    public function testSendTestEmailReportsWhetherItWasQueued(): void
    {
        Mail::fake();
        $sender = app(Sender::class);

        $this->assertTrue($sender->sendTestEmail('ok@example.org', new PlainMailable()));
    }

    public function testSendTestEmailReturnsFalseForBlocklistedAddress(): void
    {
        Mail::fake();
        GlobalEmailBlockList::create(['email' => 'blocked@example.org', 'status' => 'bounce']);

        $sender = app(Sender::class);

        $this->assertFalse($sender->sendTestEmail('blocked@example.org', new PlainMailable()));
        Mail::assertNothingSent();
    }
}
