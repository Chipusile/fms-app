<?php

namespace App\Services\Operations;

use App\Models\OdometerReading;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class OdometerService
{
    public function record(
        Vehicle $vehicle,
        int $reading,
        string $source,
        ?int $sourceReferenceId,
        CarbonInterface|string $recordedAt,
        ?int $driverId = null,
        ?string $notes = null,
    ): OdometerReading {
        $timestamp = $recordedAt instanceof CarbonInterface
            ? $recordedAt->copy()
            : Carbon::parse($recordedAt);

        $odometerReading = $this->existingReading($vehicle->tenant_id, $vehicle->id, $source, $sourceReferenceId) ?? new OdometerReading();

        $odometerReading->fill([
            'tenant_id' => $vehicle->tenant_id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driverId,
            'reading' => $reading,
            'source' => $source,
            'source_reference_id' => $sourceReferenceId,
            'recorded_at' => $timestamp,
            'notes' => $notes,
            'is_anomaly' => $this->isAnomaly(
                tenantId: $vehicle->tenant_id,
                vehicleId: $vehicle->id,
                reading: $reading,
                recordedAt: $timestamp,
                ignoreId: $odometerReading->exists ? $odometerReading->id : null,
            ),
        ]);

        if (! $odometerReading->is_anomaly) {
            $odometerReading->resolved_at = null;
            $odometerReading->resolved_by = null;
            $odometerReading->resolution_notes = null;
        }

        $odometerReading->save();

        $this->refreshVehicleReading($vehicle->id, $vehicle->tenant_id);

        return $odometerReading;
    }

    public function resolve(OdometerReading $odometerReading, User $actor, ?string $resolutionNotes = null): OdometerReading
    {
        $odometerReading->update([
            'is_anomaly' => false,
            'resolved_at' => now(),
            'resolved_by' => $actor->id,
            'resolution_notes' => $resolutionNotes,
        ]);

        $this->refreshVehicleReading($odometerReading->vehicle_id, $odometerReading->tenant_id);

        return $odometerReading->fresh(['vehicle', 'driver', 'resolver']);
    }

    public function deleteSourceReading(Vehicle $vehicle, string $source, int $sourceReferenceId): void
    {
        $reading = $this->existingReading($vehicle->tenant_id, $vehicle->id, $source, $sourceReferenceId);

        if (! $reading) {
            return;
        }

        $reading->delete();
        $this->refreshVehicleReading($vehicle->id, $vehicle->tenant_id);
    }

    private function existingReading(
        int $tenantId,
        int $vehicleId,
        string $source,
        ?int $sourceReferenceId
    ): ?OdometerReading {
        if ($sourceReferenceId === null) {
            return null;
        }

        return OdometerReading::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('vehicle_id', $vehicleId)
            ->where('source', $source)
            ->where('source_reference_id', $sourceReferenceId)
            ->first();
    }

    private function isAnomaly(
        int $tenantId,
        int $vehicleId,
        int $reading,
        CarbonInterface $recordedAt,
        ?int $ignoreId = null,
    ): bool {
        $previousReading = OdometerReading::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('vehicle_id', $vehicleId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('recorded_at', '<=', $recordedAt)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();

        if (! $previousReading) {
            return false;
        }

        if ($reading < $previousReading->reading) {
            return true;
        }

        $daysBetween = max($previousReading->recorded_at->diffInDays($recordedAt), 1);
        $maxDistance = config('fleet.odometer.max_daily_distance_km', 1200) * $daysBetween;

        return ($reading - $previousReading->reading) > $maxDistance;
    }

    private function refreshVehicleReading(int $vehicleId, int $tenantId): void
    {
        $latestReading = OdometerReading::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('vehicle_id', $vehicleId)
            ->where('is_anomaly', false)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->value('reading');

        if ($latestReading === null) {
            return;
        }

        Vehicle::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($vehicleId)
            ->update(['odometer_reading' => $latestReading]);
    }
}
