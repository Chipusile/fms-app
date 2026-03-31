<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Incident;
use App\Models\Inspection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'approval_type' => $this->approval_type,
            'requested_by' => $this->requested_by,
            'decided_by' => $this->decided_by,
            'title' => $this->title,
            'summary' => $this->summary,
            'status' => $this->status,
            'due_at' => $this->due_at?->toISOString(),
            'decided_at' => $this->decided_at?->toISOString(),
            'decision_notes' => $this->decision_notes,
            'metadata' => $this->metadata,
            'requester' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester?->id,
                'name' => $this->requester?->name,
                'email' => $this->requester?->email,
            ]),
            'decider' => $this->whenLoaded('decider', fn () => [
                'id' => $this->decider?->id,
                'name' => $this->decider?->name,
                'email' => $this->decider?->email,
            ]),
            'approvalable' => $this->whenLoaded('approvalable', fn () => $this->approvalableSummary()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function approvalableSummary(): ?array
    {
        if ($this->approvalable instanceof Inspection) {
            return [
                'type' => 'inspection',
                'id' => $this->approvalable->id,
                'reference' => $this->approvalable->inspection_number,
                'status' => $this->approvalable->status,
            ];
        }

        if ($this->approvalable instanceof Incident) {
            return [
                'type' => 'incident',
                'id' => $this->approvalable->id,
                'reference' => $this->approvalable->incident_number,
                'status' => $this->approvalable->status,
            ];
        }

        return null;
    }
}
