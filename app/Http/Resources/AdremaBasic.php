<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdremaBasic extends JsonResource
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
            'stats' => [
                'voters'                => count($this->voters),
                'voters_email_verified' => $this->voters->whereNotNull('email_verified')->count(),
                'sentMessages'          => count($this->sentMessages),
            ]
        ];
    }
}
