<?php

namespace App\Services\Maintenance;

use App\Models\Setting;
use App\Models\Vehicle;
use App\Models\VehicleComponent;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehicleComponentService
{
    public function create(array $payload): VehicleComponent
    {
        return DB::transaction(function () use ($payload) {
            $vehicle = Vehicle::query()->findOrFail($payload['vehicle_id']);
            $attributes = $this->buildAttributes($payload, $vehicle);

            $component = VehicleComponent::create($attributes);

            return $component->load(['vehicle', 'serviceProvider']);
        });
    }

    public function update(VehicleComponent $vehicleComponent, array $payload): VehicleComponent
    {
        return DB::transaction(function () use ($vehicleComponent, $payload) {
            $vehicle = Vehicle::query()->findOrFail($payload['vehicle_id'] ?? $vehicleComponent->vehicle_id);
            $attributes = $this->buildAttributes($payload, $vehicle, $vehicleComponent);

            $vehicleComponent->update($attributes);

            return $vehicleComponent->fresh(['vehicle', 'serviceProvider']);
        });
    }

    public function retire(VehicleComponent $vehicleComponent, array $payload): VehicleComponent
    {
        if (in_array($vehicleComponent->status, ['retired', 'failed'], true)) {
            throw ValidationException::withMessages([
                'status' => ['This component has already been retired from service.'],
            ]);
        }

        return DB::transaction(function () use ($vehicleComponent, $payload) {
            $vehicleComponent->update([
                'status' => $payload['status'] ?? 'retired',
                'condition_status' => 'retired',
                'removed_at' => isset($payload['removed_at']) ? Carbon::parse($payload['removed_at'])->toDateString() : now()->toDateString(),
                'removed_odometer' => $payload['removed_odometer'] ?? $vehicleComponent->removed_odometer,
                'removal_reason' => $payload['removal_reason'] ?? $vehicleComponent->removal_reason,
                'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $vehicleComponent->notes,
                'metadata' => array_merge($vehicleComponent->metadata ?? [], [
                    'retired_at' => now()->toISOString(),
                ]),
            ]);

            return $vehicleComponent->fresh(['vehicle', 'serviceProvider']);
        });
    }

    public function refreshStatuses(?int $tenantId = null): void
    {
        VehicleComponent::query()
            ->with('vehicle')
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->whereNotIn('status', ['retired', 'failed'])
            ->get()
            ->each(function (VehicleComponent $component): void {
                $computed = $this->determineStoredStatus(
                    $component->tenant_id,
                    $component->next_replacement_at,
                    $component->next_replacement_km,
                    $component->vehicle?->odometer_reading,
                    $component->status,
                );

                if ($computed !== $component->status) {
                    $component->update(['status' => $computed]);
                }
            });
    }

    /**
     * @return Collection<int, VehicleComponent>
     */
    public function dueSoonComponents(?int $tenantId = null, int $limit = 10): Collection
    {
        $this->refreshStatuses($tenantId);

        return VehicleComponent::query()
            ->with(['vehicle', 'serviceProvider'])
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->whereNotIn('status', ['retired', 'failed'])
            ->get()
            ->filter(fn (VehicleComponent $component) => ! $this->isOverdue($component) && $this->isDueSoon($component))
            ->sortBy(fn (VehicleComponent $component) => [
                $component->next_replacement_at?->getTimestamp() ?? PHP_INT_MAX,
                $component->next_replacement_km ?? PHP_INT_MAX,
            ])
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, VehicleComponent>
     */
    public function overdueComponents(?int $tenantId = null, int $limit = 10): Collection
    {
        $this->refreshStatuses($tenantId);

        return VehicleComponent::query()
            ->with(['vehicle', 'serviceProvider'])
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->whereNotIn('status', ['retired', 'failed'])
            ->get()
            ->filter(fn (VehicleComponent $component) => $this->isOverdue($component))
            ->sortBy(fn (VehicleComponent $component) => [
                $component->next_replacement_at?->getTimestamp() ?? PHP_INT_MAX,
                $component->next_replacement_km ?? PHP_INT_MAX,
            ])
            ->take($limit)
            ->values();
    }

    public function isOverdue(VehicleComponent $component): bool
    {
        $vehicleOdometer = $component->vehicle?->odometer_reading;

        return ($component->next_replacement_at && $component->next_replacement_at->endOfDay()->isPast())
            || ($component->next_replacement_km !== null && $vehicleOdometer !== null && $vehicleOdometer >= $component->next_replacement_km);
    }

    public function isDueSoon(VehicleComponent $component): bool
    {
        $daysWindow = $component->reminder_days ?? (int) Setting::getTenantValue($component->tenant_id, 'component.reminder_days', 14);
        $kmWindow = $component->reminder_km ?? (int) Setting::getTenantValue($component->tenant_id, 'component.reminder_km_buffer', 1000);
        $vehicleOdometer = $component->vehicle?->odometer_reading;

        $dueByDate = $component->next_replacement_at
            && $component->next_replacement_at->endOfDay()->greaterThan(now())
            && $component->next_replacement_at->endOfDay()->lessThanOrEqualTo(now()->copy()->addDays($daysWindow)->endOfDay());

        $dueByKm = $component->next_replacement_km !== null
            && $vehicleOdometer !== null
            && $vehicleOdometer < $component->next_replacement_km
            && ($vehicleOdometer + $kmWindow) >= $component->next_replacement_km;

        return $dueByDate || $dueByKm;
    }

    private function buildAttributes(array $payload, Vehicle $vehicle, ?VehicleComponent $existing = null): array
    {
        $status = $payload['status'] ?? $existing?->status ?? 'active';
        $installedAt = isset($payload['installed_at'])
            ? Carbon::parse($payload['installed_at'])->toDateString()
            : $existing?->installed_at?->toDateString();
        $installedOdometer = $payload['installed_odometer'] ?? $existing?->installed_odometer ?? $vehicle->odometer_reading;
        $expectedLifeDays = $payload['expected_life_days'] ?? $existing?->expected_life_days;
        $expectedLifeKm = $payload['expected_life_km'] ?? $existing?->expected_life_km;
        $nextReplacementAt = $status === 'retired' || $status === 'failed' || ! $installedAt || ! $expectedLifeDays
            ? null
            : Carbon::parse($installedAt)->addDays((int) $expectedLifeDays)->toDateString();
        $nextReplacementKm = $status === 'retired' || $status === 'failed' || $installedOdometer === null || ! $expectedLifeKm
            ? null
            : ((int) $installedOdometer + (int) $expectedLifeKm);

        return [
            'vehicle_id' => $vehicle->id,
            'service_provider_id' => $payload['service_provider_id'] ?? $existing?->service_provider_id,
            'component_number' => $existing?->component_number ?? $this->generateComponentNumber($vehicle->tenant_id),
            'component_type' => $payload['component_type'] ?? $existing?->component_type,
            'position_code' => array_key_exists('position_code', $payload) ? $payload['position_code'] : $existing?->position_code,
            'brand' => array_key_exists('brand', $payload) ? $payload['brand'] : $existing?->brand,
            'model' => array_key_exists('model', $payload) ? $payload['model'] : $existing?->model,
            'serial_number' => array_key_exists('serial_number', $payload) ? $payload['serial_number'] : $existing?->serial_number,
            'status' => $this->determineStoredStatus($vehicle->tenant_id, $nextReplacementAt, $nextReplacementKm, $vehicle->odometer_reading, $status),
            'condition_status' => $payload['condition_status'] ?? $existing?->condition_status ?? 'good',
            'installed_at' => $installedAt,
            'installed_odometer' => $installedOdometer,
            'expected_life_days' => $expectedLifeDays,
            'expected_life_km' => $expectedLifeKm,
            'reminder_days' => $payload['reminder_days'] ?? $existing?->reminder_days,
            'reminder_km' => $payload['reminder_km'] ?? $existing?->reminder_km,
            'next_replacement_at' => $nextReplacementAt,
            'next_replacement_km' => $nextReplacementKm,
            'warranty_expiry_date' => isset($payload['warranty_expiry_date'])
                ? Carbon::parse($payload['warranty_expiry_date'])->toDateString()
                : $existing?->warranty_expiry_date?->toDateString(),
            'last_inspected_at' => isset($payload['last_inspected_at'])
                ? Carbon::parse($payload['last_inspected_at'])->toDateString()
                : $existing?->last_inspected_at?->toDateString(),
            'removed_at' => isset($payload['removed_at'])
                ? Carbon::parse($payload['removed_at'])->toDateString()
                : $existing?->removed_at?->toDateString(),
            'removed_odometer' => $payload['removed_odometer'] ?? $existing?->removed_odometer,
            'removal_reason' => array_key_exists('removal_reason', $payload) ? $payload['removal_reason'] : $existing?->removal_reason,
            'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $existing?->notes,
            'metadata' => $existing?->metadata,
        ];
    }

    private function determineStoredStatus(
        int $tenantId,
        CarbonInterface|string|null $nextReplacementAt,
        ?int $nextReplacementKm,
        ?int $vehicleOdometer,
        string $status,
    ): string {
        if (in_array($status, ['retired', 'failed'], true)) {
            return $status;
        }

        $nextDate = is_string($nextReplacementAt) ? Carbon::parse($nextReplacementAt) : $nextReplacementAt;
        $isOverdue = ($nextDate && $nextDate->endOfDay()->isPast())
            || ($nextReplacementKm !== null && $vehicleOdometer !== null && $vehicleOdometer >= $nextReplacementKm);

        if ($isOverdue) {
            return 'due_replacement';
        }

        return 'active';
    }

    private function generateComponentNumber(int $tenantId): string
    {
        $prefix = config('fleet.vehicle_component.number_prefix', 'CMP');
        $count = VehicleComponent::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->withTrashed()
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }
}
