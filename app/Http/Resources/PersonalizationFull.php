<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Models\Personalization;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Personalization
 */
class PersonalizationFull extends JsonResource
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
            'owner'        => $this->owner,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'photo_url'    => $this->photo_url,
        ];
    }
}
