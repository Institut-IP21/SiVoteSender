<?php

namespace App\Http\Controllers;

use App\Http\Resources\SentMessageFull;
use App\Models\SentMessage;
use Illuminate\Http\Request;

class SentMessageController extends Controller
{

    public function show(SentMessage $sentMessage)
    {
        return new SentMessageFull($sentMessage);
    }

    public function batchStats($batchId)
    {
        $data = SentMessage::where('batch_uuid', $batchId)->get();

        if (!$data or $data->count() < 1) {
            return $this->basicResponse(404, ['error' => 'No sent messages found for this batch']);
        }

        abort_if($this->checkOwner($data->first()->voterlist->owner), 403);

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
