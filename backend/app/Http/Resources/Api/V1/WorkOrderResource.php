<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'maintenance_schedule_id' => $this->maintenance_schedule_id,
            'maintenance_request_id' => $this->maintenance_request_id,
            'vehicle_id' => $this->vehicle_id,
            'service_provider_id' => $this->service_provider_id,
            'assigned_to' => $this->assigned_to,
            'work_order_number' => $this->work_order_number,
            'title' => $this->title,
            'maintenance_type' => $this->maintenance_type,
            'priority' => $this->priority,
            'status' => $this->status,
            'due_date' => $this->due_date?->toDateString(),
            'opened_at' => $this->opened_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'odometer_reading' => $this->odometer_reading,
            'estimated_cost' => $this->estimated_cost,
            'actual_cost' => $this->actual_cost,
            'notes' => $this->notes,
            'resolution_notes' => $this->resolution_notes,
            'metadata' => $this->metadata,
            'schedule' => $this->whenLoaded('maintenanceSchedule', fn () => [
                'id' => $this->maintenanceSchedule?->id,
                'title' => $this->maintenanceSchedule?->title,
                'schedule_type' => $this->maintenanceSchedule?->schedule_type,
                'status' => $this->maintenanceSchedule?->status,
            ]),
            'request' => $this->whenLoaded('maintenanceRequest', fn () => [
                'id' => $this->maintenanceRequest?->id,
                'request_number' => $this->maintenanceRequest?->request_number,
                'status' => $this->maintenanceRequest?->status,
            ]),
            'vehicle' => $this->whenLoaded('vehicle', fn () => [
                'id' => $this->vehicle?->id,
                'registration_number' => $this->vehicle?->registration_number,
                'make' => $this->vehicle?->make,
                'model' => $this->vehicle?->model,
                'status' => $this->vehicle?->status,
            ]),
            'service_provider' => $this->whenLoaded('serviceProvider', fn () => [
                'id' => $this->serviceProvider?->id,
                'name' => $this->serviceProvider?->name,
                'provider_type' => $this->serviceProvider?->provider_type,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => [
                'id' => $this->assignee?->id,
                'name' => $this->assignee?->name,
                'email' => $this->assignee?->email,
            ]),
            'maintenance_record' => $this->whenLoaded('maintenanceRecord', fn () => new MaintenanceRecordResource($this->maintenanceRecord)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
