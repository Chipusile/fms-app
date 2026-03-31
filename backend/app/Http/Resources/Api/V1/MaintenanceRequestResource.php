<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'maintenance_schedule_id' => $this->maintenance_schedule_id,
            'vehicle_id' => $this->vehicle_id,
            'service_provider_id' => $this->service_provider_id,
            'requested_by' => $this->requested_by,
            'reviewed_by' => $this->reviewed_by,
            'request_number' => $this->request_number,
            'title' => $this->title,
            'request_type' => $this->request_type,
            'priority' => $this->priority,
            'status' => $this->status,
            'needed_by' => $this->needed_by?->toDateString(),
            'requested_at' => $this->requested_at?->toISOString(),
            'odometer_reading' => $this->odometer_reading,
            'description' => $this->description,
            'review_notes' => $this->review_notes,
            'metadata' => $this->metadata,
            'schedule' => $this->whenLoaded('schedule', fn () => [
                'id' => $this->schedule?->id,
                'title' => $this->schedule?->title,
                'schedule_type' => $this->schedule?->schedule_type,
                'status' => $this->schedule?->status,
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
            'requester' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester?->id,
                'name' => $this->requester?->name,
                'email' => $this->requester?->email,
            ]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => [
                'id' => $this->reviewer?->id,
                'name' => $this->reviewer?->name,
                'email' => $this->reviewer?->email,
            ]),
            'work_order' => $this->whenLoaded('workOrder', fn () => $this->workOrder ? [
                'id' => $this->workOrder->id,
                'work_order_number' => $this->workOrder->work_order_number,
                'status' => $this->workOrder->status,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
