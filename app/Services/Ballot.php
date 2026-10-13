<?php

namespace App\Services;

use App\Mail\BallotInvite;
use App\Mail\BallotResult;
use App\Mail\SessionInvite;
use App\Models\SentMessage;
use App\Models\Voter;
use App\Models\VoterList;
use Illuminate\Support\Facades\Log;

class Ballot
{
    public function __construct(protected Sender $sender)
    {
    }

    /**
     * @param array<int, string> $codes
     */
    public function sendInvites(VoterList $voterlist, array $codes, string $url, string $batch, string $template, string $subject, ?string $locale = null): bool
    {
        $voters = $voterlist->voters;

        Log::info(
            'Sending invite emails',
            ['count' => count($voters), 'voterlist' => $voterlist->id, 'batch' => $batch]
        );

        // Check if the voterlist is valid
        foreach ($voters as $voter) {
            if (!$voter->email) {
                Log::warning(
                    'Tried to send an invite to a voter with no email',
                    ['voter' => $voter->id, 'voterlist' => $voterlist->id]
                );
                throw new \Exception("Voter " . $voter->id . " has no email!", 1);
            }
        }

        if (count($codes) !== $voters->count()) {
            Log::warning(
                'Number of codes and voters does not match',
                ['voterlist' => $voterlist->id, 'codesCount' => count($codes), 'batch' => $batch]
            );
            throw new \Exception("Number of codes and voters does not match", 2);
        }

        // Randomize both collections
        shuffle($codes);
        $voters->shuffle();

        /** @var array<int, string> $codeFor voter id => code */
        $codeFor = [];
        foreach ($voters as $voter) {
            $codeFor[$voter->id] = (string) array_pop($codes);
        }

        $this->sender->sendEmails(
            $voterlist,
            $voters,
            fn (Voter $voter) => new BallotInvite($codeFor[$voter->id], $url, $template, $subject, $locale),
            $batch
        );

        return true;
    }

    /**
     * @param array<int, array<string, string>> $codes
     */
    public function sendSessionInvites(VoterList $voterlist, array $codes, string $batch, string $template, string $subject, ?string $locale = null): bool
    {
        $voters = $voterlist->voters;

        Log::info(
            'Sending session invite emails',
            ['count' => count($voters), 'voterlist' => $voterlist->id, 'batch' => $batch]
        );

        // Check if the voterlist is valid
        foreach ($voters as $voter) {
            if (!$voter->email) {
                Log::warning(
                    'Tried to send a session invite to a voter with no email',
                    ['voter' => $voter->id, 'voterlist' => $voterlist->id]
                );
                throw new \Exception("Voter " . $voter->id . " has no email!", 1);
            }
        }

        if (count($codes) !== $voters->count()) {
            Log::warning(
                'Number of session codes and voters does not match',
                ['voterlist' => $voterlist->id, 'codesCount' => count($codes), 'batch' => $batch]
            );
            throw new \Exception("Number of session codes and voters does not match", 2);
        }

        /** @var array<int, array<string, string>> $codeFor voter id => session code */
        $codeFor = [];
        foreach ($voters as $voter) {
            $codeFor[$voter->id] = (array) array_shift($codes);
        }

        $this->sender->sendEmails(
            $voterlist,
            $voters,
            fn (Voter $voter) => new SessionInvite($codeFor[$voter->id], $template, $subject, $locale),
            $batch
        );

        return true;
    }

    /**
     * @param array<int, string> $to
     */
    public function sendInvitesTest(array $to, string $url, string $template, string $subject, ?string $locale = null): bool
    {
        Log::info(
            'Sending test invite email',
            ['emails' => $to]
        );

        foreach ($to as $voter) {
            $code = "TESTCODE";

            $email = new BallotInvite($code, $url, $template, $subject, $locale);

            $sentMessage = $this->sender->sendTestEmail($voter, $email);
        }

        return true;
    }

    /**
     * Mail the results. With $inviteBatch the recipients are exactly the voters who got an
     * invite in that batch for this list (even if detached from the list since, as long
     * as the voter row still exists and has an address); without it, the current members.
     * Removing a voter through the API soft-deletes the row, so those voters are skipped.
     */
    public function sendResults(VoterList $voterlist, string $batch, string $template, string $subject, string $csv, string $resultLink, ?string $locale = null, ?string $inviteBatch = null): bool
    {
        if ($inviteBatch !== null) {
            $voters = Voter::query()
                ->whereIn('id', SentMessage::query()
                    ->where('voterlist_id', $voterlist->id)
                    ->where('batch_uuid', $inviteBatch)
                    ->where('type', SentMessage::TYPE_EMAIL)
                    ->select('voter_id'))
                ->whereNotNull('email')
                ->whereRaw("TRIM(email) <> ''")
                ->get();
        } else {
            $voters = $voterlist->voters;

            foreach ($voters as $voter) {
                if (!$voter->email) {
                    Log::warning(
                        'Tried to send results to a voter with no email',
                        ['voter' => $voter->id, 'voterlist' => $voterlist->id]
                    );
                    throw new \Exception("Voter " . $voter->id . " has no email!", 1);
                }
            }
        }

        Log::info(
            'Sending result emails',
            ['count' => count($voters), 'voterlist' => $voterlist->id, 'batch' => $batch, 'inviteBatch' => $inviteBatch, 'csvSize' => strlen($csv)]
        );

        $this->sender->sendEmails(
            $voterlist,
            $voters,
            fn (Voter $voter) => new BallotResult($template, $subject, $csv, $resultLink, $locale),
            $batch
        );

        return true;
    }
}
