<?php

namespace App\Services;

use App\Mail\BallotInvite;
use App\Mail\BallotResult;
use App\Models\Adrema;
use Illuminate\Support\Facades\Log;

class Ballot
{
    protected $sender;

    public function __construct(Sender $sender)
    {
        $this->sender = $sender;
    }

    public function sendInvites(Adrema $adrema, array $codes, string $url, string $batch, string $template, string $subject): bool
    {
        $voters = $adrema->voters;

        Log::info(
            'Sending invite emails',
            ['count' => count($voters), 'adrema' => $adrema->id, 'batch' => $batch]
        );

        // Check if the adrema is valid
        foreach ($voters as $voter) {
            if (!$voter->email) {
                Log::warning(
                    'Tried to send an invite to a voter with no email',
                    ['voter' => $voter->id, 'adrema' => $adrema->id]
                );
                throw new \Exception("Voter " . $voter->id . " has no email!", 1);
            }
        }

        if (count($codes) !== $voters->count()) {
            Log::warning(
                'Number of codes and voters does not match',
                ['adrema' => $adrema->id, 'codesCount' => count($codes), 'batch' => $batch]
            );
            throw new \Exception("Number of codes and voters does not match", 2);
        }

        // Randomize both collections
        shuffle($codes);
        $voters->shuffle();

        foreach ($voters as $voter) {
            $code = array_pop($codes);

            $email = new BallotInvite($code, $url, $template, $subject);

            $sentMessage = $this->sender->sendEmail($voter, $email, $adrema, $batch);

            Log::debug('Sent invite', ['voter' => $voter->id, 'batch' => $batch]);
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

    public function sendResults(Adrema $adrema, string $batch, string $template, string $subject, string $csv): bool
    {
        $voters = $adrema->voters;

        Log::info(
            'Sending result emails',
            ['count' => count($voters), 'adrema' => $adrema->id, 'batch' => $batch, 'csvSize' => strlen($csv)]
        );

        // Check if the adrema is valid
        foreach ($voters as $voter) {
            if (!$voter->email) {
                Log::warning(
                    'Tried to send an invite to a voter with no email',
                    ['voter' => $voter->id, 'adrema' => $adrema->id]
                );
                throw new \Exception("Voter " . $voter->id . " has no email!", 1);
            }
        }

        foreach ($voters as $voter) {
            $email = new BallotResult($template, $subject, $csv);

            $sentMessage = $this->sender->sendEmail($voter, $email, $adrema, $batch);

            Log::debug('Sent results', ['voter' => $voter->id, 'batch' => $batch, 'csvSize' => strlen($csv)]);
        }

        return true;
    }
}
