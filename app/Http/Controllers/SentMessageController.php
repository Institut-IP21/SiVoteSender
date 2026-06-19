<?php

namespace App\Http\Controllers;

use App\Models\VoterList;
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
        /** @var VoterList $voterList */
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

    public function batchUndeliverable(string $batchId): JsonResponse
    {
        $messages = SentMessage::query()
            ->batch($batchId)
            ->whereIn('status', [
                SentMessage::STATUS_BOUNCE,
                SentMessage::STATUS_BOUNCE_SOFT,
                SentMessage::STATUS_COMPLAINT,
            ])
            ->with('voter')
            ->get();

        if ($messages->isEmpty()) {
            // Owner can't be checked without a row; an empty batch is simply "none".
            return $this->basicResponse(200, ['data' => []]);
        }

        /** @var SentMessage $first */
        $first = $messages->first();
        /** @var VoterList $voterList */
        $voterList = $first->voterList;
        abort_if($this->checkOwner($voterList->owner), 403);

        $data = $messages->map(fn (SentMessage $m): array => [
            'voter_id'   => $m->voter_id,
            'email'      => $m->voter->email,
            'name'       => $m->voter->title,
            'status'     => $m->status,
            'status_msg' => $m->status_msg,
        ])->values()->all();

        return $this->basicResponse(200, ['data' => $data]);
    }
}
