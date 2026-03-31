<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'inspection_template_id' => $this->inspection_template_id,
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'trip_id' => $this->trip_id,
            'inspected_by' => $this->inspected_by,
            'inspection_number' => $this->inspection_number,
            'performed_at' => $this->performed_at?->toISOString(),
            'odometer_reading' => $this->odometer_reading,
            'result' => $this->result,
            'status' => $this->status,
            'total_items' => $this->total_items,
            'failed_items' => $this->failed_items,
            'critical_defects' => $this->critical_defects,
            'notes' => $this->notes,
            'resolution_notes' => $this->resolution_notes,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'metadata' => $this->metadata,
            'template' => $this->whenLoaded('template', fn () => [
                'id' => $this->template?->id,
                'name' => $this->template?->name,
                'code' => $this->template?->code,
            ]),
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
            'inspector' => $this->whenLoaded('inspector', fn () => [
                'id' => $this->inspector?->id,
                'name' => $this->inspector?->name,
                'email' => $this->inspector?->email,
            ]),
            'responses' => $this->whenLoaded('responses', fn () => $this->responses->map(fn ($response) => [
                'id' => $response->id,
                'inspection_template_item_id' => $response->inspection_template_item_id,
                'item_label' => $response->item_label,
                'response_value' => $response->response_value['value'] ?? $response->response_value,
                'is_pass' => $response->is_pass,
                'defect_severity' => $response->defect_severity,
                'defect_summary' => $response->defect_summary,
                'notes' => $response->notes,
                'sort_order' => $response->sort_order,
            ])),
            'approval_requests' => $this->whenLoaded('approvalRequests', fn () => ApprovalRequestResource::collection($this->approvalRequests)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
