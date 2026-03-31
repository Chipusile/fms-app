<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $vehicleOdometer = $this->vehicle?->odometer_reading;
        $kmUntilReplacement = $this->next_replacement_km !== null && $vehicleOdometer !== null
            ? $this->next_replacement_km - $vehicleOdometer
            : null;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'vehicle_id' => $this->vehicle_id,
            'service_provider_id' => $this->service_provider_id,
            'component_number' => $this->component_number,
            'component_type' => $this->component_type,
            'position_code' => $this->position_code,
            'brand' => $this->brand,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'status' => $this->status,
            'condition_status' => $this->condition_status,
            'installed_at' => $this->installed_at?->toDateString(),
            'installed_odometer' => $this->installed_odometer,
            'expected_life_days' => $this->expected_life_days,
            'expected_life_km' => $this->expected_life_km,
            'reminder_days' => $this->reminder_days,
            'reminder_km' => $this->reminder_km,
            'next_replacement_at' => $this->next_replacement_at?->toDateString(),
            'next_replacement_km' => $this->next_replacement_km,
            'days_until_replacement' => $this->next_replacement_at ? now()->startOfDay()->diffInDays($this->next_replacement_at->startOfDay(), false) : null,
            'km_until_replacement' => $kmUntilReplacement,
            'due_status' => $this->dueStatus($kmUntilReplacement),
            'warranty_expiry_date' => $this->warranty_expiry_date?->toDateString(),
            'last_inspected_at' => $this->last_inspected_at?->toDateString(),
            'removed_at' => $this->removed_at?->toDateString(),
            'removed_odometer' => $this->removed_odometer,
            'removal_reason' => $this->removal_reason,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'vehicle' => $this->whenLoaded('vehicle', fn () => [
                'id' => $this->vehicle?->id,
                'registration_number' => $this->vehicle?->registration_number,
                'make' => $this->vehicle?->make,
                'model' => $this->vehicle?->model,
                'odometer_reading' => $this->vehicle?->odometer_reading,
            ]),
            'service_provider' => $this->whenLoaded('serviceProvider', fn () => [
                'id' => $this->serviceProvider?->id,
                'name' => $this->serviceProvider?->name,
                'provider_type' => $this->serviceProvider?->provider_type,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function dueStatus(?int $kmUntilReplacement): string
    {
        if (in_array($this->status, ['retired', 'failed'], true)) {
            return $this->status;
        }

        $dateOverdue = $this->next_replacement_at && $this->next_replacement_at->endOfDay()->lt(now());
        $kmOverdue = $kmUntilReplacement !== null && $kmUntilReplacement <= 0;

        if ($dateOverdue || $kmOverdue) {
            return 'due_replacement';
        }

        $reminderDays = $this->reminder_days ?? (int) Setting::getTenantValue($this->tenant_id, 'component.reminder_days', 14);
        $reminderKm = $this->reminder_km ?? (int) Setting::getTenantValue($this->tenant_id, 'component.reminder_km_buffer', 1000);
        $dateUpcoming = $this->next_replacement_at
            && $this->next_replacement_at->endOfDay()->lessThanOrEqualTo(now()->copy()->addDays($reminderDays)->endOfDay());
        $kmUpcoming = $kmUntilReplacement !== null && $kmUntilReplacement <= $reminderKm;

        if ($dateUpcoming || $kmUpcoming) {
            return 'due_soon';
        }

        return 'scheduled';
    }
}
