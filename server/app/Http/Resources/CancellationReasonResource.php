<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CancellationReason */
class CancellationReasonResource extends JsonResource
{
    /** @return array{id: int, label: string, requires_note: bool} */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'label'         => $this->label,
            'requires_note' => $this->requiresNote(),
        ];
    }
}
