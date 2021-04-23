<?php

namespace Database\Seeders;

use App\Models\VoterList;
use App\Models\Verification;
use App\Models\Voter;
use Illuminate\Database\Seeder;

use Illuminate\Support\Str;

class FakeDataSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $voterlist = VoterList::factory()
            ->times(1)

            // Non-verified voters
            ->has(Voter::factory()->count(15), 'voters')

            // Non-verified voters, with phones
            ->has(Voter::factory()->count(15)->hasPhone(), 'voters')

            // Verification that has not been sent yet
            ->has(Verification::factory()->count(1), 'verifications')

            ->create();
    }
}
