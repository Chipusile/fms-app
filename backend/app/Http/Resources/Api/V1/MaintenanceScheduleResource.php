<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $vehicle = $this->whenLoaded('vehicle');
        $kmUntilDue = $this->next_due_km !== null && $this->vehicle
            ? $this->next_due_km - $this->vehicle->odometer_reading
            : null;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'vehicle_id' => $this->vehicle_id,
            'service_provider_id' => $this->service_provider_id,
            'title' => $this->title,
            'schedule_type' => $this->schedule_type,
            'status' => $this->status,
            'interval_days' => $this->interval_days,
            'interval_km' => $this->interval_km,
            'reminder_days' => $this->reminder_days,
            'reminder_km' => $this->reminder_km,
            'last_performed_at' => $this->last_performed_at?->toISOString(),
            'last_performed_km' => $this->last_performed_km,
            'next_due_at' => $this->next_due_at?->toISOString(),
            'next_due_km' => $this->next_due_km,
            'days_until_due' => $this->next_due_at ? now()->startOfDay()->diffInDays($this->next_due_at->startOfDay(), false) : null,
            'km_until_due' => $kmUntilDue,
            'due_status' => $this->dueStatus($kmUntilDue),
            'notes' => $this->notes,
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

    private function dueStatus(?int $kmUntilDue): string
    {
        if ($this->status !== 'active') {
            return $this->status;
        }

        $dateOverdue = $this->next_due_at && $this->next_due_at->endOfDay()->lt(now());
        $kmOverdue = $kmUntilDue !== null && $kmUntilDue <= 0;

        if ($dateOverdue || $kmOverdue) {
            return 'overdue';
        }

        $reminderDays = $this->reminder_days ?? (int) Setting::getValue('maintenance.reminder_days', 7);
        $reminderKm = $this->reminder_km ?? (int) Setting::getValue('maintenance.reminder_km_buffer', 500);
        $dateUpcoming = $this->next_due_at
            && $this->next_due_at->endOfDay()->lessThanOrEqualTo(now()->copy()->addDays($reminderDays)->endOfDay());
        $kmUpcoming = $kmUntilDue !== null && $kmUntilDue <= $reminderKm;

        if ($dateUpcoming || $kmUpcoming) {
            return 'due_soon';
        }

        return 'scheduled';
    }
}
