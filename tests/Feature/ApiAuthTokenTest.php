<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiAuthTokenTest extends TestCase
{
    use RefreshDatabase;

    private string $owner = 'c1c88f6a-f437-4080-80f0-fe40f596c050';

    private function callList(string $token): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders([
            'Authorization' => $token,
            'Owner'         => $this->owner,
        ])->getJson('/api/voterlist');
    }

    public function testExactTokenAccepted(): void
    {
        $this->callList('666666')->assertOk();
    }

    /** @return array<int, array<int, string>> */
    public static function rejectedVariants(): array
    {
        return [
            ['666666e0'],
            [' 666666'],
            ['666666 '],
            ['0666666'],
            ['666666.0'],
            ['+666666'],
            ['666667'],
            ['Bearer 666666'],
            [''],
        ];
    }

    #[DataProvider('rejectedVariants')]
    public function testCoercionVariantsRejected(string $token): void
    {
        $this->callList($token)->assertStatus(401);
    }
}
