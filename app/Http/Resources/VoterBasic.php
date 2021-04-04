<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VoterBasic extends JsonResource
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
            'title'          => $this->title,
            'email'          => $this->email,
            'email_verified' => $this->email_verified,
            'phone'          => $this->phone,
            'phone_verified' => $this->phone_verified,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
            'stats' => [
                'sentMessages' => count($this->sentMessages),
            ]
        ];
    }
}
