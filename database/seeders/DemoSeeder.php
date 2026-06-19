<?php

namespace Database\Seeders;

use App\Models\SentMessage;
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

        /** @var array{team_uuid:string,voterlists:array<int,array<string,mixed>>,elections:array<int,array<string,mixed>>} $manifest */
        $manifest = json_decode((string) File::get(self::MANIFEST), true);
        $owner = (string) $manifest['team_uuid'];

        /** @var array<int,list<int>> $listVoters list id => seeded voter ids */
        $listVoters = [];
        foreach ($manifest['voterlists'] as $spec) {
            $listVoters[(int) $spec['id']] = $this->seedList($owner, $spec);
        }

        $this->seedUndeliverable($manifest['elections'] ?? [], $listVoters);

        $this->command->info('Sender demo data seeded for team ' . $owner . '.');
    }

    /**
     * @param  array<string,mixed>  $spec
     * @return list<int> the seeded voter ids (for the undeliverable pass)
     */
    private function seedList(string $owner, array $spec): array
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

        return $voterIds;
    }

    /**
     * Seed named "undeliverable" invites for ballots flagged with `undeliverable` in the
     * manifest, so the Monitor screen's delivery panel has real bounced rows to show. The
     * real send path keys a batch by the ballot id, so we set batch_uuid = ballot id and
     * mark a few of the election's voters bounced/soft-bounced/complaint. Only sender-managed
     * elections have delivery data (self-managed lists distribute codes themselves).
     *
     * @param  array<int,array<string,mixed>>  $elections
     * @param  array<int,list<int>>  $listVoters
     */
    private function seedUndeliverable(array $elections, array $listVoters): void
    {
        $reasons = [
            SentMessage::STATUS_BOUNCE => 'Recipient address does not exist',
            SentMessage::STATUS_BOUNCE_SOFT => 'Mailbox full',
            SentMessage::STATUS_COMPLAINT => 'Marked as spam',
        ];
        $statuses = array_keys($reasons);

        foreach ($elections as $election) {
            if (! empty($election['self_managed']) || empty($election['voterlist_id'])) {
                continue;
            }

            $listId = (int) $election['voterlist_id'];
            $voters = $listVoters[$listId] ?? [];

            foreach ((array) ($election['ballots'] ?? []) as $ballot) {
                $count = (int) ($ballot['undeliverable'] ?? 0);
                if ($count <= 0 || $voters === []) {
                    continue;
                }

                // Land the bounces on the tail of the list (most likely unverified) so a
                // bounced voter isn't also shown as email-verified.
                $pick = array_values(array_slice($voters, -min($count, count($voters))));

                foreach ($pick as $i => $voterId) {
                    $status = $statuses[$i % count($statuses)];
                    SentMessage::factory()->notSuccessful()->create([
                        'voter_id' => $voterId,
                        'voterlist_id' => $listId,
                        'batch_uuid' => (string) $ballot['id'],
                        'status' => $status,
                        'status_msg' => $reasons[$status],
                    ]);

                    // Hard bounces & complaints block the address (mirrors the SNS webhook);
                    // soft bounces are transient and leave the voter deliverable.
                    if ($status !== SentMessage::STATUS_BOUNCE_SOFT) {
                        Voter::whereKey($voterId)->update(['email_blocked' => true]);
                    }
                }
            }
        }
    }
}
