<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdremaFull extends JsonResource
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
            'id'           => $this->id,
            'title'        => $this->title,
            'owner'        => $this->owner,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'voters'       => VoterBasic::collection($this->voters),
            'sentMessages' => SentMessageBasic::collection($this->sentMessages),
            'verifications' => VerificationBasic::collection($this->verifications),
            'stats' => [
                'voters'                => count($this->voters),
                'voters_email_verified' => $this->voters->whereNotNull('email_verified')->count(),
                'sentMessages'          => count($this->sentMessages),
            ]
        ];
    }
}
