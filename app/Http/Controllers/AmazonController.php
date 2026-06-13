<?php

namespace App\Http\Controllers;

use App\Http\Resources\SentMessageFull;
use App\Models\GlobalEmailBlockList;
use App\Models\SentMessage;
use App\Models\Voter;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

use Log;
use Http;

class AmazonController extends Controller
{

    const TYPE_SUB_CONFIRM = 'SubscriptionConfirmation';
    const TYPE_NOTIFICATION = 'Notification';

    const SUBTYPE_DELIVERY = 'Delivery';
    const SUBTYPE_BOUNCE = 'Bounce';
    const SUBTYPE_COMPLAINT = 'Complaint';

    public function post(Request $request): void
    {
        /** @var object{Type: string, SubscribeURL: string, Message: string, notificationType: string}|null $data */
        $data = json_decode($request->getContent());
        // Log::debug('SNS Hook Called', [$request->getContent()]);

        if (!isset($data->Type)) {
            Log::error('SNS Hook does not have a type!');
            return;
        }

        switch ($data->Type) {
            case self::TYPE_SUB_CONFIRM:
                $this->_processSubConfirmation($data->SubscribeURL);
                break;

            case self::TYPE_NOTIFICATION:
                /** @var object{notificationType: string, mail: object{destination: list<string>}, bounce: object{bounceType: string, bounceSubType: string, bouncedRecipients: list<object{diagnosticCode: string}>}, complaint: object{timestamp: string, complaintFeedbackType: string}, delivery: object} $msg */
                $msg = json_decode($data->Message);

                match ($msg->notificationType) {
                    self::SUBTYPE_DELIVERY => $this->_processDelivery($msg),
                    self::SUBTYPE_BOUNCE => $this->_processBounce($msg),
                    self::SUBTYPE_COMPLAINT => $this->_processComplaint($msg),
                    default => Log::alert('Received unknown SUBTYPE from SNS!', [$msg->notificationType, $data->Message]),
                };

                break;

            default:
                Log::alert('Received unknown TYPE from SNS!', [$data->Type, $data->Message]);
                break;
        }
    }

    //
    //
    //

    /**
     * @param object{notificationType: string, mail: object{destination: list<string>}, bounce: object{bounceType: string, bounceSubType: string, bouncedRecipients: list<object{diagnosticCode: string}>}, complaint: object{timestamp: string, complaintFeedbackType: string}, delivery: object} $msg
     */
    private function _processDelivery(object $msg): void
    {
        $emails = $msg->mail->destination;

        $sentMsg = $this->_getSentMessage($emails[0]);
        if (!$sentMsg) {
            return;
        }

        $sentMsg->status = SentMessage::STATUS_DELIVERED;
        $sentMsg->successful = true;
        $sentMsg->save();

        $sentMsg->voter->email_blocked = false;
        $sentMsg->voter->save();

        return;
    }

    /**
     * @param object{notificationType: string, mail: object{destination: list<string>}, bounce: object{bounceType: string, bounceSubType: string, bouncedRecipients: list<object{diagnosticCode: string}>}, complaint: object{timestamp: string, complaintFeedbackType: string}, delivery: object} $msg
     */
    private function _processBounce(object $msg): void
    {
        $emails = $msg->mail->destination;
        $type   = $msg->bounce->bounceType; // Permenent / Transient

        $sentMsg = $this->_getSentMessage($emails[0]);
        if (!$sentMsg) {
            return;
        }

        switch ($type) {
            case 'Permanent':
            case 'Undetermined':
                // Subtypes:
                // General, NoEmail, Suppressed, OnAccountSuppressionList

                $sentMsg->status = SentMessage::STATUS_BOUNCE;
                $sentMsg->successful = false;
                $sentMsg->status_msg =
                    $msg->bounce->bouncedRecipients[0]->diagnosticCode;
                $sentMsg->save();

                $sentMsg->voter->email_blocked = true;
                $sentMsg->voter->save();

                GlobalEmailBlockList::create([
                    'email'      => $emails[0],
                    'status'     => GlobalEmailBlockList::STATUS_BOUNCE,
                    'status_msg' => $msg->bounce->bouncedRecipients[0]->diagnosticCode
                ]);
                break;

            case 'Transient':

                // Subtypes
                // General, MailboxFull, MessageTooLarge, ContentRejected, AttachmentRejected

                $sentMsg->status = SentMessage::STATUS_BOUNCE_SOFT;
                $sentMsg->successful = false;
                $sentMsg->status_msg = $msg->bounce->bounceSubType;
                $sentMsg->save();

                $sentMsg->voter->email_blocked = false;
                $sentMsg->voter->save();
                break;

            default:
                Log::error('Unknown bounce type!', [json_encode($msg)]);
                return;
        }
    }

    /**
     * @param object{notificationType: string, mail: object{destination: list<string>}, bounce: object{bounceType: string, bounceSubType: string, bouncedRecipients: list<object{diagnosticCode: string}>}, complaint: object{timestamp: string, complaintFeedbackType: string}, delivery: object} $msg
     */
    private function _processComplaint(object $msg): void
    {
        $time = $msg->complaint->timestamp;
        $emails = $msg->mail->destination;

        $sentMsg = $this->_getSentMessage($emails[0], [
            SentMessage::STATUS_BOUNCE_SOFT,
            SentMessage::STATUS_SENT,
            SentMessage::STATUS_DELIVERED
        ]);
        if (!$sentMsg) {
            return;
        }

        $sentMsg->status = SentMessage::STATUS_COMPLAINT;
        $sentMsg->successful = false;
        $sentMsg->status_msg = $msg->complaint->complaintFeedbackType;
        $sentMsg->save();

        $sentMsg->voter->email_blocked = true;
        $sentMsg->voter->save();

        GlobalEmailBlockList::create([
            'email'      => $emails[0],
            'status'     => GlobalEmailBlockList::STATUS_COMPLAINT,
            'status_msg' => $msg->complaint->complaintFeedbackType
        ]);
    }

    /**
     * @param list<string>|null $statusList
     */
    private function _getSentMessage(string $email, ?array $statusList = null): SentMessage|false
    {
        if (!$statusList) {
            $statusList = [
                SentMessage::STATUS_BOUNCE_SOFT,
                SentMessage::STATUS_SENT
            ];
        }

        $sentMsg = SentMessage::where('type', SentMessage::TYPE_EMAIL)
            ->whereHas('voter', function (Builder $query) use ($email): void {
                /** @var Builder<Voter> $query */
                $query->where('email', $email);
            })
            ->whereIn('status', $statusList)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$sentMsg) {
            Log::warning('Got a SNS delivery notification but could not find sentMessage', [$email]);
            return false;
        }

        return $sentMsg;
    }

    /**
     * When you first setup a SNS HTTPs hook it calls with a confirmation URL. This handles it automatically.
     *
     * @param string $url
     * @return void
     */
    private function _processSubConfirmation(string $url): void
    {
        // SSRF guard: only ever fetch genuine AWS SNS confirmation URLs, never an
        // attacker-supplied host smuggled in via the (untrusted) request body.
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host   = parse_url($url, PHP_URL_HOST);
        if ($scheme !== 'https' || !is_string($host) || !preg_match('/^sns\.[a-z0-9-]+\.amazonaws\.com$/', $host)) {
            Log::warning('Rejected SNS subscription confirmation URL (not an AWS SNS host)', [$url]);
            return;
        }

        $response = Http::get($url);

        if (!$response->ok()) {
            Log::alert('Could not confirm SNS hook subscription!', [$response->body()]);
            return;
        }

        Log::info('SNS Hook subscription confirmation DONE!');
    }
}
