<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'vehicle_id' => $this->vehicle_id,
            'maintenance_schedule_id' => $this->maintenance_schedule_id,
            'work_order_id' => $this->work_order_id,
            'service_provider_id' => $this->service_provider_id,
            'recorded_by' => $this->recorded_by,
            'summary' => $this->summary,
            'maintenance_type' => $this->maintenance_type,
            'completed_at' => $this->completed_at?->toISOString(),
            'odometer_reading' => $this->odometer_reading,
            'downtime_hours' => $this->downtime_hours,
            'labor_cost' => $this->labor_cost,
            'parts_cost' => $this->parts_cost,
            'total_cost' => $this->total_cost,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'vehicle' => $this->whenLoaded('vehicle', fn () => [
                'id' => $this->vehicle?->id,
                'registration_number' => $this->vehicle?->registration_number,
                'make' => $this->vehicle?->make,
                'model' => $this->vehicle?->model,
            ]),
            'schedule' => $this->whenLoaded('maintenanceSchedule', fn () => [
                'id' => $this->maintenanceSchedule?->id,
                'title' => $this->maintenanceSchedule?->title,
                'schedule_type' => $this->maintenanceSchedule?->schedule_type,
            ]),
            'work_order' => $this->whenLoaded('workOrder', fn () => [
                'id' => $this->workOrder?->id,
                'work_order_number' => $this->workOrder?->work_order_number,
                'status' => $this->workOrder?->status,
            ]),
            'service_provider' => $this->whenLoaded('serviceProvider', fn () => [
                'id' => $this->serviceProvider?->id,
                'name' => $this->serviceProvider?->name,
                'provider_type' => $this->serviceProvider?->provider_type,
            ]),
            'recorder' => $this->whenLoaded('recorder', fn () => [
                'id' => $this->recorder?->id,
                'name' => $this->recorder?->name,
                'email' => $this->recorder?->email,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
