<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\MovementAccountability
 */
class MovementAccountabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'movement_id' => $this->movement_id,
            'movement' => new MovementResource($this->whenLoaded('movement')),
            'rendered_at' => $this->rendered_at?->format('Y-m-d') ?? (string) $this->rendered_at,
            'amount' => $this->amount !== null ? (string) $this->amount : null,
            'description' => $this->description,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'attachments' => AccountabilityAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
