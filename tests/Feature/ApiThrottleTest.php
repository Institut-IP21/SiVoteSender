<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiThrottleTest extends TestCase
{
    use RefreshDatabase;

    private string $owner = 'c1c88f6a-f437-4080-80f0-fe40f596c050';

    private function hit(string $token, string $ip = '10.0.0.1'): \Illuminate\Testing\TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders(['Authorization' => $token, 'Owner' => $this->owner])
            ->getJson('/api/voterlist');
    }

    // Also proves the throttle runs BEFORE ApiAuth: otherwise 401s would never be counted.
    public function test_a_locked_out_client_is_refused_even_with_the_correct_token(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->hit('guess-' . $i)->assertStatus(401);
        }
        $this->hit('guess-x')->assertStatus(429);
        $this->hit('666666')->assertStatus(429);
        // Another client network is unaffected.
        $this->hit('666666', '10.0.0.2')->assertOk();
    }

    public function test_ipv6_guesses_are_counted_per_64(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->hit('guess-' . $i, '2001:db8:1:2::' . dechex($i + 1))->assertStatus(401);
        }
        $this->hit('666666', '2001:db8:1:2:ffff::9')->assertStatus(429);
        $this->hit('666666', '2001:db8:1:3::1')->assertOk();
    }

    public function test_valid_traffic_is_not_capped(): void
    {
        for ($i = 0; $i < 150; $i++) {
            $this->hit('666666')->assertOk();
        }
        $this->hit('guess')->assertStatus(401);
    }
}
