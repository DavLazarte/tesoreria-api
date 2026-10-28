<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Movement
 */
class MovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'movement_date' => $this->movement_date?->format('Y-m-d') ?? (string) $this->movement_date,
            'amount' => (string) $this->amount,
            'concept' => $this->concept,
            'reference' => $this->reference,
            'note' => $this->note,
            'from_fund_id' => $this->from_fund_id,
            'from_fund' => new FundResource($this->whenLoaded('fromFund')),
            'to_fund_id' => $this->to_fund_id,
            'to_fund' => new FundResource($this->whenLoaded('toFund')),
            'entity_id' => $this->entity_id,
            'entity' => new EntityResource($this->whenLoaded('entity')),
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'is_voided' => $this->isVoided(),
            'void' => new MovementVoidResource($this->whenLoaded('void')),
            'attachments' => MovementAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
