<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SentMessageBasic extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
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
