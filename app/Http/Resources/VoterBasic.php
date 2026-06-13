<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Models\Voter;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Voter
 */
class VoterBasic extends JsonResource
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
