<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TripMessage */
class TripMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'role'       => $this->sender_role,
            'body'       => $this->body ?? '',
            'image_url'  => $this->imageUrl(),
            'created_at' => $this->created_at?->format('H:i'),
        ];
    }
}
