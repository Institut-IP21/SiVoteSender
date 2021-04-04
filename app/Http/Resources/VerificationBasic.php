<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VerificationBasic extends JsonResource
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
            'template'     => $this->template,
            'subject'      => $this->subject,
            'sent_at'      => $this->sent_at,
            'redirect_url' => $this->redirect_url,
            'adrema'   => new AdremaBasic($this->adrema),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'stats' => [
                'sentMessages' => count($this->sentMessages),
            ]
        ];
    }
}
