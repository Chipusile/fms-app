<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'applies_to' => $this->applies_to,
            'status' => $this->status,
            'requires_review_on_critical' => $this->requires_review_on_critical,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'response_type' => $item->response_type,
                'is_required' => $item->is_required,
                'triggers_defect_on_fail' => $item->triggers_defect_on_fail,
                'sort_order' => $item->sort_order,
                'metadata' => $item->metadata,
            ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
