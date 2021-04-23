<?php

namespace App\Services;

use App\Mail\Verification as MailVerification;
use App\Models\Verification as ModelsVerification;
use App\Models\Voter;
use URL;
use Str;
use Log;

class Verification
{

    protected $sender;

    public function __construct(Sender $sender)
    {
        $this->sender = $sender;
    }

    public function sendInvites(ModelsVerification $verification): bool
    {
        $batch = (string) Str::uuid();

        $voters = $verification->voterlist->voters;

        Log::info(
            'Sending verification emails',
            ['count' => count($voters), 'verification' => $verification->id]
        );

        foreach ($voters as $voter) {
            if (!$voter->email) {
                Log::warning(
                    'Tried to send a verification invite to a voter with no email',
                    ['voter' => $voter->id, 'verification' => $verification->id]
                );
                continue;
            }

            $url = URL::temporarySignedRoute(
                'verification.verify',
                now()->addDays(30),
                [
                    'verification' => $verification,
                    'voter'  => $voter
                ]
            );

            $email = new MailVerification($verification, $url);

            $sentMessage = $this->sender->sendEmail($voter, $email, $verification, $batch);
        }

        $verification->sent_at = now();
        $verification->save();

        return true;
    }

    public function sendInviteSingle(Voter $voter, $subject, $template): bool
    {
        $batch = (string) Str::uuid();

        Log::info(
            'Sending SINGLE verification email',
        );

        if (!$voter->email) {
            Log::warning(
                'Tried to send a verification invite to a voter with no email',
                ['voter' => $voter->id]
            );
            return false;
        }

        $url = URL::temporarySignedRoute(
            'verification.verify.single.email',
            now()->addDays(30),
            [
                'voter'  => $voter
            ]
        );

        $email = new MailVerification(null, $url, $subject, $template);

        $sentMessage = $this->sender->sendEmail(
            $voter,
            $email,
            $voter->voterlists->first(),
            $batch
        );

        return true;
    }

    public function sendTestInvites(ModelsVerification $verification, array $voters): bool
    {

        Log::info(
            'Sending verification TEST emails',
            ['count' => count($voters), 'verification' => $verification->id]
        );

        foreach ($voters as $voter) {

            $url = 'TESTURL';

            $email = new MailVerification($verification, $url);

            $sentMessage = $this->sender->sendTestEmail($voter, $email);
        }

        return true;
    }
}
