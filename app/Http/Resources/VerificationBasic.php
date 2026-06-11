<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Verification
 */
class VerificationBasic extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(\Illuminate\Http\Request $request): array
    {
        return [
            'id'           => $this->id,
            'template'     => $this->template,
            'subject'      => $this->subject,
            'sent_at'      => $this->sent_at,
            'redirect_url' => $this->redirect_url,
            'voterlist'   => new VoterListBasic($this->voterlist),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'stats' => [
                'sentMessages' => count($this->sentMessages),
            ]
        ];
    }
}
