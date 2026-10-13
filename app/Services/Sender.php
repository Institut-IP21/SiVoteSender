<?php

namespace App\Services;

use App\Jobs\SendVoterEmail;
use App\Models\VoterList;
use App\Models\GlobalEmailBlockList;
use App\Models\SentMessage;
use App\Models\Verification as ModelsVerification;
use App\Models\Voter;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class Sender
{

    public function sendEmail(Voter $voter, Mailable $mailable, VoterList|ModelsVerification $order, string $batch = ''): ?SentMessage
    {
        if ($voter->email_blocked) {
            Log::info('Sending blocked', ['to' => $voter->id, 'type' => $mailable::class]);
            return null;
        }

        switch ($order::class) {
            case ModelsVerification::class:
                $voterlist = $order->voterlist_id;
                $verification = $order->id;
                break;

            case VoterList::class:
            default:
                $voterlist = $order->id;
                $verification = null;
                break;
        }


        /** @var string $voterEmail */
        $voterEmail = $voter->email;

        $onBlocklist = $this->isBlocklisted($voterEmail);

        $sentMessage = SentMessage::create(
            [
                'type'            => SentMessage::TYPE_EMAIL,
                'voter_id'        => $voter->id,
                'voterlist_id'       => $voterlist,
                'batch_uuid'      => $batch,
                'verification_id' => $verification,
                'successful'      => false,
                'status'          => $onBlocklist ? SentMessage::STATUS_BLOCKED : SentMessage::STATUS_SENT
            ]
        );

        if ($onBlocklist) {
            Log::warning('Trying to send to blocked email address', ['voter' => $voter->id]);
            return $sentMessage;
        }

        SendVoterEmail::dispatch($voterEmail, $mailable, $sentMessage->id);

        Log::info('Queued message', ['to' => $voter->id, 'type' => $mailable::class]);

        return $sentMessage;
    }

    /** Rows per multi-row INSERT (sent_messages and jobs). Keeps each statement well under max_allowed_packet. */
    private const BULK_CHUNK = 500;

    /**
     * Record + queue one e-mail per voter in a constant number of queries (10,000 voters
     * well inside a web request). Same rules as {@see sendEmail()}, applied to a whole
     * batch: a voter flagged email_blocked is skipped entirely; an address on the global
     * block list gets a SentMessage marked blocked but no job.
     *
     * Intended to run INSIDE the caller's transaction. Jobs are never runnable before
     * that transaction commits and vanish if it rolls back: on the database queue they
     * are inserted into the same transaction (see {@see pushJobs()}), otherwise they are
     * dispatched with afterCommit.
     *
     * @param iterable<Voter> $voters
     * @param \Closure(Voter): Mailable $mailableFor
     * @return int number of SentMessage rows recorded
     */
    public function sendEmails(VoterList $voterlist, iterable $voters, \Closure $mailableFor, string $batch): int
    {
        $targets = [];
        foreach ($voters as $voter) {
            if ($voter->email_blocked) {
                continue;
            }
            $targets[] = $voter;
        }
        if ($targets === []) {
            return 0;
        }

        $blocked = $this->blocklistedSet(array_map(fn (Voter $v): string => (string) $v->email, $targets));
        $now = now();

        $rows = [];
        foreach ($targets as $voter) {
            $rows[] = [
                'type'            => SentMessage::TYPE_EMAIL,
                'voter_id'        => $voter->id,
                'voterlist_id'    => $voterlist->id,
                'batch_uuid'      => $batch,
                'verification_id' => null,
                'successful'      => false,
                'status'          => isset($blocked[$this->normalizeEmail((string) $voter->email)])
                    ? SentMessage::STATUS_BLOCKED
                    : SentMessage::STATUS_SENT,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }
        foreach (array_chunk($rows, self::BULK_CHUNK) as $chunk) {
            DB::table('sent_messages')->insert($chunk);
        }

        // One read for the generated ids (a fresh list+batch holds exactly these rows).
        /** @var \Illuminate\Support\Collection<int|string, int|string> $ids */
        $ids = DB::table('sent_messages')
            ->where('voterlist_id', $voterlist->id)
            ->where('batch_uuid', $batch)
            ->pluck('id', 'voter_id');

        $jobs = [];
        foreach ($targets as $i => $voter) {
            if ($rows[$i]['status'] === SentMessage::STATUS_BLOCKED) {
                continue;
            }
            $jobs[] = new SendVoterEmail((string) $voter->email, $mailableFor($voter), (int) $ids[$voter->id]);
        }
        $this->pushJobs($jobs);

        // SentMessage::create() would touch the voter + list per row; do it in bulk.
        foreach (array_chunk(array_map(fn (Voter $v): int => $v->id, $targets), 1000) as $voterIds) {
            DB::table('voters')->whereIn('id', $voterIds)->update(['updated_at' => $now]);
        }
        $voterlist->touch();

        Log::info('Queued messages', [
            'voterlist' => $voterlist->id, 'batch' => $batch, 'recorded' => count($rows), 'queued' => count($jobs),
        ]);

        return count($rows);
    }

    /**
     * @param list<SendVoterEmail> $jobs
     */
    private function pushJobs(array $jobs): void
    {
        if ($jobs === []) {
            return;
        }

        if ($this->jobsShareDefaultConnection()) {
            // Multi-row INSERTs on the SAME connection as the surrounding transaction:
            // committed with it, discarded with it, so afterCommit holds by construction.
            foreach (array_chunk($jobs, self::BULK_CHUNK) as $chunk) {
                Queue::connection()->bulk($chunk);
            }

            return;
        }

        foreach ($jobs as $job) {
            dispatch($job); // SendVoterEmail::$afterCommit = true
        }
    }

    private function jobsShareDefaultConnection(): bool
    {
        /** @var string $default */
        $default = config('queue.default');
        /** @var array<string, mixed>|null $conn */
        $conn = config("queue.connections.$default");

        return is_array($conn)
            && ($conn['driver'] ?? null) === 'database'
            && in_array($conn['connection'] ?? null, [null, config('database.default')], true);
    }

    /**
     * @param list<string> $emails
     * @return array<string, true> normalised blocked addresses among $emails
     */
    private function blocklistedSet(array $emails): array
    {
        $candidates = [];
        foreach ($emails as $email) {
            $candidates[$email] = true;
            $candidates[trim($email)] = true;
            $candidates[$this->normalizeEmail($email)] = true;
        }

        $set = [];
        foreach (array_chunk(array_keys($candidates), 1000) as $chunk) {
            foreach (GlobalEmailBlockList::whereIn('email', $chunk)->pluck('email') as $blockedEmail) {
                $set[$this->normalizeEmail((string) $blockedEmail)] = true;
            }
        }

        return $set;
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function sendTestEmail(string $to, Mailable $mailable): bool
    {
        $queued = $this->checkAndSend($to, $mailable);

        Log::info('Sent test message', ['type' => $mailable::class, 'queued' => $queued]);

        return $queued;
    }

    public function checkAndSend(string $to, Mailable $mailable): bool
    {
        if ($this->isBlocklisted($to)) {
            Log::warning('Trying to send to blocked email address (test path)');
            return false;
        }

        SendVoterEmail::dispatch($to, $mailable);

        return true;
    }

    public function isBlocklisted(string $email): bool
    {
        return GlobalEmailBlockList::where('email', $email)->exists();
    }
}
