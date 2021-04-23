<?php

namespace App\Services;

use App\Models\VoterList;
use App\Models\GlobalEmailBlockList;
use App\Models\SentMessage;
use App\Models\Verification as ModelsVerification;
use App\Models\Voter;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Sender
{

    public function sendEmail(Voter $voter, Mailable $mailable, $order, string $batch = ''): SentMessage
    {
        if ($voter->email_blocked) {
            Log::info('Sending blocked', ['to' => $voter->id, 'type' => get_class($mailable)]);
            return false;
        }

        switch (get_class($order)) {
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


        $result = $this->checkAndSend($voter->email, $mailable);

        Log::info('Sent message', ['to' => $voter->id, 'type' => get_class($mailable)]);

        $sentMessage = SentMessage::create(
            [
                'type'            => SentMessage::TYPE_EMAIL,
                'voter_id'        => $voter->id,
                'voterlist_id'       => $voterlist,
                'batch_uuid'      => $batch,
                'verification_id' => $verification,
                'successful'      => false,
                'status'          => $result ? SentMessage::STATUS_SENT : SentMessage::STATUS_BLOCKED
            ]
        );

        return $sentMessage;
    }

    public function sendTestEmail(string $to, Mailable $mailable): bool
    {
        $result = $this->checkAndSend($to, $mailable);

        Log::info('Sent test message', ['to' => $to, 'type' => get_class($mailable)]);

        return true;
    }

    public function checkAndSend(string $to, Mailable $mailable)
    {
        $blocked = GlobalEmailBlockList::where('email', $to)->first();

        if ($blocked) {
            Log::warning('Trying to send to blocked email address', [$blocked->email]);
            return false;
        }

        return Mail::to($to)->queue($mailable);
    }
}
