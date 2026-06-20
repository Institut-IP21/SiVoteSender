<?php

namespace App\Support\Alerts;

use App\Contracts\EmailFailureAlerter;
use App\Support\EmailFailureDigest;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Alternate transport: e-mails the digest to config('mail-alerts.mail_to').
 * Caveat: a total mail outage would also drop this alert — prefer 'log'.
 */
class MailAlerter implements EmailFailureAlerter
{
    public function alert(EmailFailureDigest $digest): void
    {
        /** @var string|null $to */
        $to = config('mail-alerts.mail_to');

        if (empty($to)) {
            Log::warning('mail-alerts transport is "mail" but mail_to is not configured; skipping alert.');

            return;
        }

        Mail::raw($digest->toText(), function (Message $message) use ($to, $digest): void {
            $message->to($to)->subject('[SiVote] ' . $digest->summaryLine());
        });
    }
}
