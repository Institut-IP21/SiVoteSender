<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Closure;
use Illuminate\Support\Facades\Log;

class VerifySnsMessage
{
    /**
     * Fails closed on an empty allowlist: a valid AWS signature only proves *some* AWS account, not a topic we own.
     *
     * @param Request $request
     */
    public function handle($request, Closure $next)
    {
        try {
            $message = Message::fromJsonString($request->getContent());
        } catch (\InvalidArgumentException $e) {
            Log::warning('SNS webhook rejected: malformed message', ['error' => $e->getMessage()]);
            return response('Invalid SNS message.', 403);
        }

        $allowedArns = (array) config('services.sns.topic_arns', []);
        $topicArn = $message->offsetExists('TopicArn') ? (string) $message['TopicArn'] : '';

        if ($allowedArns === []) {
            Log::warning('SNS webhook rejected: SNS_TOPIC_ARNS is not configured (fail closed).');
            return response('SNS topic allowlist not configured.', 403);
        }

        if (!in_array($topicArn, $allowedArns, true)) {
            Log::warning('SNS webhook rejected: TopicArn not allowlisted', ['topic_arn' => $topicArn]);
            return response('Unrecognized SNS topic.', 403);
        }

        $validator = app(MessageValidator::class);
        if (!$validator->isValid($message)) {
            Log::warning('SNS webhook rejected: invalid signature');
            return response('Invalid SNS signature.', 403);
        }

        return $next($request);
    }
}
