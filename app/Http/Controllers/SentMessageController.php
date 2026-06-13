<?php

namespace App\Http\Controllers;

use App\Http\Resources\SentMessageFull;
use App\Models\SentMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SentMessageController extends Controller
{

    public function show(SentMessage $sentMessage): SentMessageFull
    {
        return new SentMessageFull($sentMessage);
    }

    public function batchStats(string $batchId): JsonResponse
    {
        $data = SentMessage::where('batch_uuid', $batchId)->get();

        if ($data->isEmpty()) {
            return $this->basicResponse(404, ['error' => 'No sent messages found for this batch']);
        }

        /** @var SentMessage $first */
        $first = $data->first();
        /** @var \App\Models\VoterList $voterList */
        $voterList = $first->voterList;
        abort_if($this->checkOwner($voterList->owner), 403);

        // @TODO optimize this!
        return $this->basicResponse(
            200,
            [
                'stats' => [
                    'sent'   => count($data),
                    'errors' => count($data->where('successful', false))
                ]
            ]
        );
    }
}
