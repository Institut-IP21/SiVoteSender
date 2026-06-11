<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SentMessage
 */
class SentMessageBasic extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(\Illuminate\Http\Request $request): array
    {
        return [
            'id'             => $this->id,
            'contact'        => $this->contact,
            'type'           => $this->type,
            'batch_uuid'     => $this->batch_uuid,
            'successful'     => $this->successful,
            'status'         => $this->status,
            'status_msg'     => $this->status_msg,
            'isVerification' => (bool) $this->verification_id
        ];
    }
}
