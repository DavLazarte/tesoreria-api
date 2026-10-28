<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\MovementVoid
 */
class MovementVoidResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reason' => $this->reason,
            'voided_at' => $this->voided_at?->toIso8601String(),
            'voided_by' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
