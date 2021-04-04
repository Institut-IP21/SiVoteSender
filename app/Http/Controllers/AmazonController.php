<?php

namespace App\Http\Controllers;

use App\Http\Resources\SentMessageFull;
use App\Models\GlobalEmailBlockList;
use App\Models\SentMessage;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

use Log;
use Http;

/**
 * @Controller(prefix="sns")
 */
class AmazonController extends Controller
{

    const TYPE_SUB_CONFIRM = 'SubscriptionConfirmation';
    const TYPE_NOTIFICATION = 'Notification';

    const SUBTYPE_DELIVERY = 'Delivery';
    const SUBTYPE_BOUNCE = 'Bounce';
    const SUBTYPE_COMPLAINT = 'Complaint';

    /**
     * @Post("/webhook", as="email.sns.notifications")
     */
    public function post(Request $request)
    {
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
                $msg = json_decode($data->Message);

                switch ($msg->notificationType) {
                    case self::SUBTYPE_DELIVERY:
                        $this->_processDelivery($msg);
                        break;

                    case self::SUBTYPE_BOUNCE:
                        $this->_processBounce($msg);
                        break;

                    case self::SUBTYPE_COMPLAINT:
                        $this->_processComplaint($msg);
                        break;

                    default:
                        Log::alert('Received unknown SUBTYPE from SNS!', [$data->notificationType, $data->Message]);
                        break;
                }

                break;

            default:
                Log::alert('Received unknown TYPE from SNS!', [$data->Type, $data->Message]);
                break;
        }
    }

    //
    //
    //

    private function _processDelivery(\stdClass $msg)
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

    private function _processBounce(\stdClass $msg)
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
                break;
        }
    }

    private function _processComplaint(\stdClass $msg)
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

    private function _getSentMessage($email, ?array $statusList = null)
    {
        if (!$statusList) {
            $statusList = [
                SentMessage::STATUS_BOUNCE_SOFT,
                SentMessage::STATUS_SENT
            ];
        }

        $sentMsg = SentMessage::where('type', SentMessage::TYPE_EMAIL)
            ->whereHas('voter', function (Builder $query) use ($email) {
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
    private function _processSubConfirmation(string $url)
    {
        $response = Http::get($url);

        if (!$response->ok()) {
            Log::alert('Could not confirm SNS hook subscription!', [$response->body()]);
            return;
        }

        Log::info('SNS Hook subscription confirmation DONE!');
    }
}
