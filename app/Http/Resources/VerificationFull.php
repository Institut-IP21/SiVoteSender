<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Models\Verification;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Verification
 */
class VerificationFull extends JsonResource
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
            'voterlist'       => new VoterListBasic($this->voterlist),
            'sentMessages' => SentMessageBasic::collection($this->sentMessages),
            'template'     => $this->template,
            'subject'      => $this->subject,
            'sent_at'      => $this->sent_at,
            'redirect_url' => $this->redirect_url,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'stats' => [
                'sentMessages' => count($this->sentMessages),
            ]
        ];
    }
}
