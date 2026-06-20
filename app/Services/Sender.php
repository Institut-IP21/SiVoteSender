<?php

namespace App\Services;

use App\Jobs\SendVoterEmail;
use App\Models\VoterList;
use App\Models\GlobalEmailBlockList;
use App\Models\SentMessage;
use App\Models\Verification as ModelsVerification;
use App\Models\Voter;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;

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

        // Record first so the queued send can mark it failed if it gives up.
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
            Log::warning('Trying to send to blocked email address', [$voterEmail]);
            return $sentMessage;
        }

        SendVoterEmail::dispatch($voterEmail, $mailable, $sentMessage->id);

        Log::info('Queued message', ['to' => $voter->id, 'type' => $mailable::class]);

        return $sentMessage;
    }

    public function sendTestEmail(string $to, Mailable $mailable): bool
    {
        $queued = $this->checkAndSend($to, $mailable);

        Log::info('Sent test message', ['to' => $to, 'type' => $mailable::class, 'queued' => $queued]);

        return $queued;
    }

    /**
     * Dispatch a fault-tolerant send unless the recipient is globally blocked.
     * Returns false when blocked, true when the send was queued. Used by the
     * untracked test-email path; the tracked voter path (sendEmail) dispatches
     * directly so it can link the SentMessage it just created.
     */
    public function checkAndSend(string $to, Mailable $mailable): bool
    {
        if ($this->isBlocklisted($to)) {
            Log::warning('Trying to send to blocked email address', [$to]);
            return false;
        }

        SendVoterEmail::dispatch($to, $mailable);

        return true;
    }

    /**
     * On the global (bounce/complaint) block list? Used by both send paths.
     */
    public function isBlocklisted(string $email): bool
    {
        return GlobalEmailBlockList::where('email', $email)->exists();
    }
}
