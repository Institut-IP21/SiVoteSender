<?php

namespace Database\Seeders;

use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Dev demo data — sender side. Reads the manifest written by web_app's DemoSeeder
 * (the orchestrator that owns the spec + every shared UUID) and builds the matching
 * voter lists and voters in the sender database.
 *
 * Run AFTER web_app's DemoSeeder (seed-demo.sh enforces the order). The apps are
 * linked only by the team UUID = `owner`.
 *
 * Per list the manifest gives total / verified / bounced counts:
 *   - `verified` of the voters get an `email_verified` timestamp,
 *   - `bounced` of them get `email_blocked = true`,
 *   - the rest are plain unverified voters.
 */
class DemoSeeder extends Seeder
{
    private const MANIFEST = '/tmp/evote-demo-manifest.json';

    public function run(): void
    {
        if (! File::exists(self::MANIFEST)) {
            $this->command->error('Manifest ' . self::MANIFEST . ' not found — run web_app DemoSeeder first.');

            return;
        }

        /** @var array{team_uuid:string,voterlists:array<int,array<string,mixed>>} $manifest */
        $manifest = json_decode((string) File::get(self::MANIFEST), true);
        $owner = (string) $manifest['team_uuid'];

        foreach ($manifest['voterlists'] as $spec) {
            $this->seedList($owner, $spec);
        }

        $this->command->info('Sender demo data seeded for team ' . $owner . '.');
    }

    /**
     * @param  array<string,mixed>  $spec
     */
    private function seedList(string $owner, array $spec): void
    {
        $list = VoterList::factory()->create([
            'id' => $spec['id'],
            'owner' => $owner,
            'title' => $spec['title'],
        ]);

        $total = (int) ($spec['total'] ?? 0);
        $verified = (int) ($spec['verified'] ?? 0);
        $bounced = (int) ($spec['bounced'] ?? 0);

        $voterIds = [];
        for ($i = 0; $i < $total; $i++) {
            $factory = Voter::factory();

            // The first `$verified` voters confirmed their email.
            if ($i < $verified) {
                $factory = $factory->verifiedEmail();
            }

            // The first `$bounced` voters' email bounced / is blocked.
            $voter = $factory->create([
                'email_blocked' => $i < $bounced,
            ]);

            $voterIds[] = $voter->id;
        }

        // VoterList ↔ Voter is many-to-many via the pivot.
        $list->voters()->attach($voterIds);
    }
}
