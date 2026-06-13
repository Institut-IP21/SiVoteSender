<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Models\SentMessage;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SentMessage
 */
class SentMessageFull extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'contact'         => $this->contact,
            'type'            => $this->type,
            'voter'           => VoterBasic::collection($this->voter),
            'voterlist'          => VoterListBasic::collection($this->voterlist),
            'batch_uuid'      => $this->batch_uuid,
            'successful'      => $this->successful,
            'status'          => $this->status,
            'status_msg'      => $this->status_msg,
            'verification_id' => $this->verification_id,
        ];
    }
}
