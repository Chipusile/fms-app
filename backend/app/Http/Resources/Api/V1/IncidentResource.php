<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'trip_id' => $this->trip_id,
            'reported_by' => $this->reported_by,
            'assigned_to' => $this->assigned_to,
            'incident_number' => $this->incident_number,
            'incident_type' => $this->incident_type,
            'severity' => $this->severity,
            'status' => $this->status,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'reported_at' => $this->reported_at?->toISOString(),
            'location' => $this->location,
            'description' => $this->description,
            'immediate_action' => $this->immediate_action,
            'injury_count' => $this->injury_count,
            'estimated_cost' => $this->estimated_cost,
            'resolution_notes' => $this->resolution_notes,
            'closed_at' => $this->closed_at?->toISOString(),
            'metadata' => $this->metadata,
            'vehicle' => $this->whenLoaded('vehicle', fn () => [
                'id' => $this->vehicle?->id,
                'registration_number' => $this->vehicle?->registration_number,
                'make' => $this->vehicle?->make,
                'model' => $this->vehicle?->model,
            ]),
            'driver' => $this->whenLoaded('driver', fn () => [
                'id' => $this->driver?->id,
                'name' => $this->driver?->name,
                'license_number' => $this->driver?->license_number,
            ]),
            'trip' => $this->whenLoaded('trip', fn () => [
                'id' => $this->trip?->id,
                'trip_number' => $this->trip?->trip_number,
                'status' => $this->trip?->status,
            ]),
            'reporter' => $this->whenLoaded('reporter', fn () => [
                'id' => $this->reporter?->id,
                'name' => $this->reporter?->name,
                'email' => $this->reporter?->email,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee?->id,
                'name' => $this->assignee?->name,
                'email' => $this->assignee?->email,
            ]),
            'approval_requests' => $this->whenLoaded('approvalRequests', fn () => ApprovalRequestResource::collection($this->approvalRequests)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
