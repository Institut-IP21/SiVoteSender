<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Voter
 */
class VoterBasic extends JsonResource
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
