<?php

namespace App\Services;

use App\Mail\BallotInvite;
use App\Mail\BallotResult;
use App\Mail\SessionInvite;
use App\Models\VoterList;
use Illuminate\Support\Facades\Log;

class Ballot
{
    protected $sender;

    public function __construct(Sender $sender)
    {
        $this->sender = $sender;
    }

    public function sendInvites(VoterList $voterlist, array $codes, string $url, string $batch, string $template, string $subject): bool
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

        foreach ($voters as $voter) {
            $code = array_pop($codes);

            $email = new BallotInvite($code, $url, $template, $subject);

            $sentMessage = $this->sender->sendEmail($voter, $email, $voterlist, $batch);

            Log::debug('Sent invite', ['voter' => $voter->id, 'batch' => $batch]);
        }

        return true;
    }

    public function sendSessionInvites(VoterList $voterlist, array $codes, string $batch, string $template, string $subject): bool
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

        foreach ($voters as $voter) {
            $code = array_shift($codes);

            $email = new SessionInvite($code, $template, $subject);

            $sentMessage = $this->sender->sendEmail($voter, $email, $voterlist, $batch);

            Log::debug('Sent session invite', ['voter' => $voter->id, 'batch' => $batch]);
        }

        return true;
    }

    public function sendInvitesTest(array $to, string $url, string $template, string $subject): bool
    {
        Log::info(
            'Sending test invite email',
            ['emails' => $to]
        );

        foreach ($to as $voter) {
            $code = "TESTCODE";

            $email = new BallotInvite($code, $url, $template, $subject);

            $sentMessage = $this->sender->sendTestEmail($voter, $email);
        }

        return true;
    }

    public function sendResults(VoterList $voterlist, string $batch, string $template, string $subject, string $csv, string $resultLink): bool
    {
        $voters = $voterlist->voters;

        Log::info(
            'Sending result emails',
            ['count' => count($voters), 'voterlist' => $voterlist->id, 'batch' => $batch, 'csvSize' => strlen($csv)]
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

        foreach ($voters as $voter) {
            $email = new BallotResult($template, $subject, $csv, $resultLink);

            $sentMessage = $this->sender->sendEmail($voter, $email, $voterlist, $batch);

            Log::debug('Sent results', ['voter' => $voter->id, 'batch' => $batch, 'csvSize' => strlen($csv)]);
        }

        return true;
    }
}
