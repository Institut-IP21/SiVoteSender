<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__test_client_ip', fn () => request()->ip());
    }

    public function testSpoofedForwardedForFromUntrustedPeerIsIgnored(): void
    {
        $response = $this->call(
            'GET',
            '/__test_client_ip',
            [],
            [],
            [],
            [
                'REMOTE_ADDR'          => '203.0.113.9',
                'HTTP_X_FORWARDED_FOR' => '10.9.9.9',
            ],
        );

        $response->assertOk();
        $this->assertSame('203.0.113.9', $response->getContent());
    }

    public function testForwardedForFromTheTrustedProxyIsHonored(): void
    {
        $response = $this->call(
            'GET',
            '/__test_client_ip',
            [],
            [],
            [],
            [
                'REMOTE_ADDR'          => '127.0.0.1',
                'HTTP_X_FORWARDED_FOR' => '198.51.100.7',
            ],
        );

        $response->assertOk();
        $this->assertSame('198.51.100.7', $response->getContent());
    }
}
