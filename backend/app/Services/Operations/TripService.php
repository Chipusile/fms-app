<?php

namespace App\Services\Operations;

use App\Models\Driver;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripService
{
    public function __construct(
        private readonly OdometerService $odometerService,
    ) {
    }

    public function create(array $payload, User $actor): Trip
    {
        return DB::transaction(function () use ($payload, $actor) {
            $vehicle = $this->resolveVehicle($payload['vehicle_id']);
            $driver = $this->resolveDriver($payload['driver_id']);
            $requiresApproval = $this->requiresApproval();
            $status = $requiresApproval ? 'requested' : 'approved';

            if ($status === 'approved') {
                $this->ensureAvailability(
                    vehicleId: $vehicle->id,
                    driverId: $driver->id,
                    scheduledStart: Carbon::parse($payload['scheduled_start']),
                    scheduledEnd: Carbon::parse($payload['scheduled_end']),
                );
            }

            $trip = Trip::create([
                ...$payload,
                'requested_by' => $actor->id,
                'approved_by' => $status === 'approved' ? $actor->id : null,
                'status' => $status,
                'trip_number' => $this->generateTripNumber($actor->tenant_id),
                'metadata' => [
                    'approval_required' => $requiresApproval,
                ],
            ]);

            return $trip->load(['vehicle', 'driver', 'requester', 'approver']);
        });
    }

    public function update(Trip $trip, array $payload): Trip
    {
        if (in_array($trip->status, ['completed', 'cancelled', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Completed, cancelled, or rejected trips cannot be edited.'],
            ]);
        }

        $vehicleId = $payload['vehicle_id'] ?? $trip->vehicle_id;
        $driverId = $payload['driver_id'] ?? $trip->driver_id;
        $scheduledStart = isset($payload['scheduled_start']) ? Carbon::parse($payload['scheduled_start']) : $trip->scheduled_start;
        $scheduledEnd = isset($payload['scheduled_end']) ? Carbon::parse($payload['scheduled_end']) : $trip->scheduled_end;

        if ($trip->status === 'approved') {
            $this->ensureAvailability($vehicleId, $driverId, $scheduledStart, $scheduledEnd, $trip->id);
        }

        $trip->update($payload);

        return $trip->fresh(['vehicle', 'driver', 'requester', 'approver']);
    }

    public function approve(Trip $trip, User $actor, ?string $notes = null): Trip
    {
        if ($trip->status !== 'requested') {
            throw ValidationException::withMessages([
                'status' => ['Only requested trips can be approved.'],
            ]);
        }

        $this->ensureAvailability(
            $trip->vehicle_id,
            $trip->driver_id,
            $trip->scheduled_start,
            $trip->scheduled_end,
            $trip->id,
        );

        $metadata = $trip->metadata ?? [];
        $metadata['approval_note'] = $notes;

        $trip->update([
            'status' => 'approved',
            'approved_by' => $actor->id,
            'rejection_reason' => null,
            'metadata' => $metadata,
        ]);

        return $trip->fresh(['vehicle', 'driver', 'requester', 'approver']);
    }

    public function reject(Trip $trip, User $actor, string $reason): Trip
    {
        if ($trip->status !== 'requested') {
            throw ValidationException::withMessages([
                'status' => ['Only requested trips can be rejected.'],
            ]);
        }

        $metadata = $trip->metadata ?? [];
        $metadata['rejected_by'] = $actor->id;

        $trip->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'metadata' => $metadata,
        ]);

        return $trip->fresh(['vehicle', 'driver', 'requester', 'approver']);
    }

    public function start(Trip $trip, User $actor, int $startOdometer, CarbonInterface|string|null $actualStart = null): Trip
    {
        if ($trip->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => ['Only approved trips can be started.'],
            ]);
        }

        $vehicle = $trip->vehicle()->firstOrFail();

        if ($vehicle->status !== 'active') {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Trips can only start when the assigned vehicle is active.'],
            ]);
        }

        if ($startOdometer < $vehicle->odometer_reading) {
            throw ValidationException::withMessages([
                'start_odometer' => ['Start odometer cannot be less than the vehicle current odometer reading.'],
            ]);
        }

        $timestamp = $actualStart ? Carbon::parse($actualStart) : now();

        $trip->update([
            'status' => 'in_progress',
            'actual_start' => $timestamp,
            'start_odometer' => $startOdometer,
        ]);

        $this->odometerService->record(
            vehicle: $vehicle,
            reading: $startOdometer,
            source: 'trip_start',
            sourceReferenceId: $trip->id,
            recordedAt: $timestamp,
            driverId: $trip->driver_id,
            notes: 'Captured at trip start.',
        );

        return $trip->fresh(['vehicle', 'driver', 'requester', 'approver']);
    }

    public function complete(
        Trip $trip,
        User $actor,
        int $endOdometer,
        CarbonInterface|string|null $actualEnd = null,
        ?string $notes = null,
    ): Trip {
        if ($trip->status !== 'in_progress') {
            throw ValidationException::withMessages([
                'status' => ['Only trips in progress can be completed.'],
            ]);
        }

        if ($trip->start_odometer === null) {
            throw ValidationException::withMessages([
                'start_odometer' => ['The trip cannot be completed because a start odometer was not captured.'],
            ]);
        }

        if ($endOdometer <= $trip->start_odometer) {
            throw ValidationException::withMessages([
                'end_odometer' => ['End odometer must be greater than the trip start odometer.'],
            ]);
        }

        $timestamp = $actualEnd ? Carbon::parse($actualEnd) : now();
        $vehicle = $trip->vehicle()->firstOrFail();

        $trip->update([
            'status' => 'completed',
            'actual_end' => $timestamp,
            'end_odometer' => $endOdometer,
            'distance_km' => $endOdometer - $trip->start_odometer,
            'notes' => $notes ?: $trip->notes,
        ]);

        $this->odometerService->record(
            vehicle: $vehicle,
            reading: $endOdometer,
            source: 'trip_end',
            sourceReferenceId: $trip->id,
            recordedAt: $timestamp,
            driverId: $trip->driver_id,
            notes: 'Captured at trip completion.',
        );

        return $trip->fresh(['vehicle', 'driver', 'requester', 'approver']);
    }

    public function cancel(Trip $trip, string $reason): Trip
    {
        if (in_array($trip->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Completed or cancelled trips cannot be cancelled again.'],
            ]);
        }

        $trip->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]);

        return $trip->fresh(['vehicle', 'driver', 'requester', 'approver']);
    }

    private function requiresApproval(): bool
    {
        return (bool) Setting::getValue('approvals.trip_approval_required', true);
    }

    private function resolveVehicle(int $vehicleId): Vehicle
    {
        $vehicle = Vehicle::query()->findOrFail($vehicleId);

        if ($vehicle->status !== 'active') {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Trips can only be scheduled against active vehicles.'],
            ]);
        }

        return $vehicle;
    }

    private function resolveDriver(int $driverId): Driver
    {
        $driver = Driver::query()->findOrFail($driverId);

        if ($driver->status !== 'active') {
            throw ValidationException::withMessages([
                'driver_id' => ['Trips can only be scheduled against active drivers.'],
            ]);
        }

        return $driver;
    }

    private function ensureAvailability(
        int $vehicleId,
        int $driverId,
        CarbonInterface $scheduledStart,
        CarbonInterface $scheduledEnd,
        ?int $ignoreTripId = null,
    ): void {
        $overlapQuery = Trip::query()
            ->when($ignoreTripId, fn ($query) => $query->whereKeyNot($ignoreTripId))
            ->whereIn('status', ['approved', 'in_progress'])
            ->where(function ($query) use ($vehicleId, $driverId) {
                $query->where('vehicle_id', $vehicleId)
                    ->orWhere('driver_id', $driverId);
            })
            ->where('scheduled_start', '<', $scheduledEnd)
            ->where('scheduled_end', '>', $scheduledStart)
            ->get(['id', 'vehicle_id', 'driver_id']);

        $vehicleOverlap = $overlapQuery->firstWhere('vehicle_id', $vehicleId);
        $driverOverlap = $overlapQuery->firstWhere('driver_id', $driverId);

        $errors = [];

        if ($vehicleOverlap) {
            $errors['vehicle_id'][] = 'The selected vehicle already has an overlapping approved or active trip.';
        }

        if ($driverOverlap) {
            $errors['driver_id'][] = 'The selected driver already has an overlapping approved or active trip.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function generateTripNumber(int $tenantId): string
    {
        $year = now()->format('Y');
        $prefix = config('fleet.trip.number_prefix', 'TRP').'-'.$year.'-';

        $lastTripNumber = Trip::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('trip_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('trip_number');

        $nextSequence = 1;

        if ($lastTripNumber) {
            $sequence = (int) str($lastTripNumber)->afterLast('-')->toString();
            $nextSequence = $sequence + 1;
        }

        return $prefix.str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
