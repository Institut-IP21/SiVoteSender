<?php

namespace App\Http\Middleware;

use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Closure;
use Illuminate\Support\Facades\Log;

class VerifySnsMessage
{
    /**
     * Verify the request is a genuine, cryptographically-signed AWS SNS message
     * before any handler is allowed to act on it. When SNS_TOPIC_ARNS is set, the
     * message must also originate from an allowlisted topic. The signature check is
     * the authentication for this externally-called webhook (AWS cannot send the
     * app's own API token / Owner header).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try {
            $message = Message::fromJsonString($request->getContent());
        } catch (\InvalidArgumentException $e) {
            Log::warning('SNS webhook rejected: malformed message', ['error' => $e->getMessage()]);
            return response('Invalid SNS message.', 403);
        }

        $validator = new MessageValidator();
        if (!$validator->isValid($message)) {
            Log::warning('SNS webhook rejected: invalid signature');
            return response('Invalid SNS signature.', 403);
        }

        $allowedArns = (array) config('services.sns.topic_arns', []);
        $topicArn = $message->offsetExists('TopicArn') ? (string) $message['TopicArn'] : '';

        if ($allowedArns !== []) {
            if (!in_array($topicArn, $allowedArns, true)) {
                Log::warning('SNS webhook rejected: TopicArn not allowlisted', ['topic_arn' => $topicArn]);
                return response('Unrecognized SNS topic.', 403);
            }
        } else {
            Log::warning('SNS webhook: SNS_TOPIC_ARNS not configured; accepting any validly-signed SNS topic.');
        }

        return $next($request);
    }
}
