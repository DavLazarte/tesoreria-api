<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AccountabilityAttachment
 */
class AccountabilityAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $movementId = $this->accountability?->movement_id;

        return [
            'id' => $this->id,
            'accountability_id' => $this->accountability_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'sha256' => $this->sha256,
            'uploader' => new UserResource($this->whenLoaded('uploader')),
            'download_url' => $movementId
                ? url("/api/movements/{$movementId}/accountabilities/{$this->accountability_id}/attachments/{$this->id}")
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
