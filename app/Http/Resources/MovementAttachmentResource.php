<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\MovementAttachment
 */
class MovementAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'movement_id' => $this->movement_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'sha256' => $this->sha256,
            'uploader' => new UserResource($this->whenLoaded('uploader')),
            'download_url' => url("/api/movements/{$this->movement_id}/attachments/{$this->id}"),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
