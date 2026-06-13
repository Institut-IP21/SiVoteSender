<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Personalization
 */
class PersonalizationFull extends JsonResource
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
            'owner'        => $this->owner,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'photo_url'    => $this->photo_url,
        ];
    }
}
