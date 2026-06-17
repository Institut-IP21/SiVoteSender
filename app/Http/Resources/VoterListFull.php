<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Models\VoterList;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VoterList
 */
class VoterListFull extends JsonResource
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
                'voters_blocked'        => $this->voters->where('email_blocked', true)->count(),
                'sentMessages'          => count($this->sentMessages),
            ]
        ];
    }
}
