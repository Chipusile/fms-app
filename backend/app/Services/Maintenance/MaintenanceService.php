<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\Operations\OdometerService;
use App\Services\Workflow\NotificationService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly OdometerService $odometerService,
    ) {
    }

    public function createSchedule(array $payload): MaintenanceSchedule
    {
        return DB::transaction(function () use ($payload) {
            $vehicle = Vehicle::query()->findOrFail($payload['vehicle_id']);
            $attributes = $this->buildScheduleAttributes($payload, $vehicle);

            $schedule = MaintenanceSchedule::create($attributes);

            return $schedule->load(['vehicle', 'serviceProvider']);
        });
    }

    public function updateSchedule(MaintenanceSchedule $schedule, array $payload): MaintenanceSchedule
    {
        return DB::transaction(function () use ($schedule, $payload) {
            $vehicle = Vehicle::query()->findOrFail($payload['vehicle_id'] ?? $schedule->vehicle_id);
            $attributes = $this->buildScheduleAttributes($payload, $vehicle, $schedule);

            $schedule->update($attributes);

            return $schedule->fresh(['vehicle', 'serviceProvider']);
        });
    }

    public function createWorkOrder(array $payload, User $actor): WorkOrder
    {
        return DB::transaction(function () use ($payload, $actor) {
            $schedule = isset($payload['maintenance_schedule_id'])
                ? MaintenanceSchedule::query()->findOrFail($payload['maintenance_schedule_id'])
                : null;

            $workOrder = WorkOrder::create([
                'tenant_id' => $actor->tenant_id,
                'maintenance_schedule_id' => $schedule?->id,
                'maintenance_request_id' => $payload['maintenance_request_id'] ?? null,
                'vehicle_id' => $payload['vehicle_id'],
                'service_provider_id' => $payload['service_provider_id'] ?? $schedule?->service_provider_id,
                'assigned_to' => $payload['assigned_to'] ?? null,
                'work_order_number' => $this->generateWorkOrderNumber($actor->tenant_id),
                'title' => $payload['title'],
                'maintenance_type' => $payload['maintenance_type'] ?? $schedule?->schedule_type ?? 'preventive',
                'priority' => $payload['priority'],
                'status' => 'open',
                'due_date' => isset($payload['due_date']) ? Carbon::parse($payload['due_date'])->toDateString() : $schedule?->next_due_at?->toDateString(),
                'opened_at' => now(),
                'estimated_cost' => $payload['estimated_cost'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'metadata' => [
                    'schedule_linked' => $schedule !== null,
                ],
            ]);

            $this->notifyAssignee($workOrder, $actor);

            return $workOrder->load(['maintenanceSchedule', 'vehicle', 'serviceProvider', 'assignee', 'maintenanceRecord']);
        });
    }

    public function updateWorkOrder(WorkOrder $workOrder, array $payload): WorkOrder
    {
        if (in_array($workOrder->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Completed or cancelled work orders cannot be modified.'],
            ]);
        }

        return DB::transaction(function () use ($workOrder, $payload) {
            $schedule = isset($payload['maintenance_schedule_id'])
                ? MaintenanceSchedule::query()->findOrFail($payload['maintenance_schedule_id'])
                : $workOrder->maintenanceSchedule;

            $workOrder->update([
                'maintenance_schedule_id' => $payload['maintenance_schedule_id'] ?? $workOrder->maintenance_schedule_id,
                'maintenance_request_id' => $payload['maintenance_request_id'] ?? $workOrder->maintenance_request_id,
                'vehicle_id' => $payload['vehicle_id'] ?? $workOrder->vehicle_id,
                'service_provider_id' => $payload['service_provider_id'] ?? $schedule?->service_provider_id ?? $workOrder->service_provider_id,
                'assigned_to' => $payload['assigned_to'] ?? $workOrder->assigned_to,
                'title' => $payload['title'] ?? $workOrder->title,
                'maintenance_type' => $payload['maintenance_type'] ?? $schedule?->schedule_type ?? $workOrder->maintenance_type,
                'priority' => $payload['priority'] ?? $workOrder->priority,
                'due_date' => isset($payload['due_date'])
                    ? Carbon::parse($payload['due_date'])->toDateString()
                    : $workOrder->due_date?->toDateString(),
                'estimated_cost' => $payload['estimated_cost'] ?? $workOrder->estimated_cost,
                'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $workOrder->notes,
            ]);

            return $workOrder->fresh(['maintenanceSchedule', 'vehicle', 'serviceProvider', 'assignee', 'maintenanceRecord']);
        });
    }

    public function startWorkOrder(WorkOrder $workOrder): WorkOrder
    {
        if ($workOrder->status !== 'open') {
            throw ValidationException::withMessages([
                'status' => ['Only open work orders can be started.'],
            ]);
        }

        $vehicle = $workOrder->vehicle()->firstOrFail();

        if ($vehicle->status === 'decommissioned') {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Decommissioned vehicles cannot enter maintenance execution.'],
            ]);
        }

        return DB::transaction(function () use ($workOrder, $vehicle) {
            $workOrder->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            if ($vehicle->status === 'active') {
                $vehicle->update(['status' => 'maintenance']);
            }

            return $workOrder->fresh(['maintenanceSchedule', 'vehicle', 'serviceProvider', 'assignee', 'maintenanceRecord']);
        });
    }

    public function completeWorkOrder(WorkOrder $workOrder, array $payload, User $actor): WorkOrder
    {
        if (in_array($workOrder->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only active work orders can be completed.'],
            ]);
        }

        return DB::transaction(function () use ($workOrder, $payload, $actor) {
            $completedAt = isset($payload['completed_at'])
                ? Carbon::parse($payload['completed_at'])
                : now();

            $actualCost = $payload['actual_cost']
                ?? $workOrder->estimated_cost
                ?? ((float) ($payload['labor_cost'] ?? 0) + (float) ($payload['parts_cost'] ?? 0));

            $workOrder->update([
                'status' => 'completed',
                'completed_at' => $completedAt,
                'actual_cost' => $actualCost,
                'odometer_reading' => $payload['odometer_reading'] ?? $workOrder->odometer_reading,
                'resolution_notes' => $payload['resolution_notes'] ?? null,
                'metadata' => array_merge($workOrder->metadata ?? [], [
                    'downtime_hours' => $payload['downtime_hours'] ?? null,
                ]),
            ]);

            $this->createMaintenanceRecord($workOrder, $payload, $actor, $completedAt, (float) $actualCost);
            $this->updateScheduleAfterCompletion($workOrder, $completedAt, $payload['odometer_reading'] ?? null);
            $this->recordCompletionOdometer($workOrder, $payload['odometer_reading'] ?? null, $completedAt);
            $this->releaseVehicleFromMaintenanceIfSafe($workOrder->vehicle()->firstOrFail(), $workOrder->id);

            return $workOrder->fresh(['maintenanceSchedule', 'vehicle', 'serviceProvider', 'assignee', 'maintenanceRecord']);
        });
    }

    public function cancelWorkOrder(WorkOrder $workOrder, ?string $resolutionNotes = null): WorkOrder
    {
        if (in_array($workOrder->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only active work orders can be cancelled.'],
            ]);
        }

        return DB::transaction(function () use ($workOrder, $resolutionNotes) {
            $wasStarted = $workOrder->status === 'in_progress';

            $workOrder->update([
                'status' => 'cancelled',
                'resolution_notes' => $resolutionNotes,
            ]);

            if ($wasStarted) {
                $this->releaseVehicleFromMaintenanceIfSafe($workOrder->vehicle()->firstOrFail(), $workOrder->id);
            }

            return $workOrder->fresh(['maintenanceSchedule', 'vehicle', 'serviceProvider', 'assignee', 'maintenanceRecord']);
        });
    }

    /**
     * @return Collection<int, MaintenanceSchedule>
     */
    public function upcomingSchedules(int $limit = 10): Collection
    {
        return MaintenanceSchedule::query()
            ->with(['vehicle', 'serviceProvider'])
            ->where('status', 'active')
            ->get()
            ->filter(fn (MaintenanceSchedule $schedule) => ! $this->isScheduleOverdue($schedule) && $this->isScheduleUpcoming($schedule))
            ->sortBy(fn (MaintenanceSchedule $schedule) => [
                $schedule->next_due_at?->getTimestamp() ?? PHP_INT_MAX,
                $schedule->next_due_km ?? PHP_INT_MAX,
            ])
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, MaintenanceSchedule>
     */
    public function overdueSchedules(int $limit = 10): Collection
    {
        return MaintenanceSchedule::query()
            ->with(['vehicle', 'serviceProvider'])
            ->where('status', 'active')
            ->get()
            ->filter(fn (MaintenanceSchedule $schedule) => $this->isScheduleOverdue($schedule))
            ->sortBy(fn (MaintenanceSchedule $schedule) => [
                $schedule->next_due_at?->getTimestamp() ?? PHP_INT_MAX,
                $schedule->next_due_km ?? PHP_INT_MAX,
            ])
            ->take($limit)
            ->values();
    }

    public function isScheduleOverdue(MaintenanceSchedule $schedule): bool
    {
        $vehicle = $schedule->vehicle;

        $dueByDate = $schedule->next_due_at !== null && $schedule->next_due_at->endOfDay()->isPast();
        $dueByKm = $schedule->next_due_km !== null
            && $vehicle !== null
            && $vehicle->odometer_reading >= $schedule->next_due_km;

        return $dueByDate || $dueByKm;
    }

    public function isScheduleUpcoming(MaintenanceSchedule $schedule): bool
    {
        $vehicle = $schedule->vehicle;
        $daysWindow = $schedule->reminder_days ?? (int) Setting::getTenantValue($schedule->tenant_id, 'maintenance.reminder_days', 7);
        $kmWindow = $schedule->reminder_km ?? (int) Setting::getTenantValue($schedule->tenant_id, 'maintenance.reminder_km_buffer', 500);

        $upcomingByDate = $schedule->next_due_at !== null
            && $schedule->next_due_at->greaterThan(now())
            && $schedule->next_due_at->lessThanOrEqualTo(now()->copy()->addDays($daysWindow));

        $upcomingByKm = $schedule->next_due_km !== null
            && $vehicle !== null
            && $vehicle->odometer_reading < $schedule->next_due_km
            && ($vehicle->odometer_reading + $kmWindow) >= $schedule->next_due_km;

        return $upcomingByDate || $upcomingByKm;
    }

    private function buildScheduleAttributes(array $payload, Vehicle $vehicle, ?MaintenanceSchedule $existing = null): array
    {
        $intervalDays = $payload['interval_days'] ?? $existing?->interval_days;
        $intervalKm = $payload['interval_km'] ?? $existing?->interval_km;
        $lastPerformedAt = isset($payload['last_performed_at'])
            ? Carbon::parse($payload['last_performed_at'])
            : $existing?->last_performed_at;
        $lastPerformedKm = $payload['last_performed_km'] ?? $existing?->last_performed_km ?? $vehicle->odometer_reading;
        $status = $payload['status'] ?? $existing?->status ?? 'active';

        return [
            'tenant_id' => $existing?->tenant_id,
            'vehicle_id' => $vehicle->id,
            'service_provider_id' => $payload['service_provider_id'] ?? $existing?->service_provider_id,
            'title' => $payload['title'] ?? $existing?->title,
            'schedule_type' => $payload['schedule_type'] ?? $existing?->schedule_type,
            'status' => $status,
            'interval_days' => $intervalDays,
            'interval_km' => $intervalKm,
            'reminder_days' => $payload['reminder_days'] ?? $existing?->reminder_days,
            'reminder_km' => $payload['reminder_km'] ?? $existing?->reminder_km,
            'last_performed_at' => $lastPerformedAt,
            'last_performed_km' => $lastPerformedKm,
            'next_due_at' => $status === 'completed' || ! $intervalDays
                ? null
                : ($lastPerformedAt ? $lastPerformedAt->copy()->addDays((int) $intervalDays) : now()->copy()->addDays((int) $intervalDays)),
            'next_due_km' => $status === 'completed' || ! $intervalKm
                ? null
                : ((int) $lastPerformedKm + (int) $intervalKm),
            'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $existing?->notes,
        ];
    }

    private function createMaintenanceRecord(
        WorkOrder $workOrder,
        array $payload,
        User $actor,
        CarbonInterface $completedAt,
        float $actualCost,
    ): MaintenanceRecord {
        $laborCost = (float) ($payload['labor_cost'] ?? 0);
        $partsCost = (float) ($payload['parts_cost'] ?? max($actualCost - $laborCost, 0));

        return MaintenanceRecord::updateOrCreate(
            ['work_order_id' => $workOrder->id],
            [
                'tenant_id' => $workOrder->tenant_id,
                'vehicle_id' => $workOrder->vehicle_id,
                'maintenance_schedule_id' => $workOrder->maintenance_schedule_id,
                'service_provider_id' => $workOrder->service_provider_id,
                'recorded_by' => $actor->id,
                'summary' => $workOrder->title,
                'maintenance_type' => $workOrder->maintenance_type,
                'completed_at' => $completedAt,
                'odometer_reading' => $payload['odometer_reading'] ?? $workOrder->odometer_reading,
                'downtime_hours' => $payload['downtime_hours'] ?? null,
                'labor_cost' => $laborCost,
                'parts_cost' => $partsCost,
                'total_cost' => $actualCost,
                'notes' => $payload['resolution_notes'] ?? $workOrder->resolution_notes ?? $workOrder->notes,
                'metadata' => [
                    'source' => 'work_order_completion',
                ],
            ]
        );
    }

    private function updateScheduleAfterCompletion(WorkOrder $workOrder, CarbonInterface $completedAt, ?int $odometerReading): void
    {
        $schedule = $workOrder->maintenanceSchedule;

        if (! $schedule) {
            return;
        }

        $schedule->update([
            'last_performed_at' => $completedAt,
            'last_performed_km' => $odometerReading ?? $schedule->last_performed_km,
            'next_due_at' => $schedule->interval_days
                ? $completedAt->copy()->addDays((int) $schedule->interval_days)
                : null,
            'next_due_km' => $schedule->interval_km && $odometerReading !== null
                ? $odometerReading + (int) $schedule->interval_km
                : $schedule->next_due_km,
            'status' => 'active',
        ]);
    }

    private function recordCompletionOdometer(WorkOrder $workOrder, ?int $odometerReading, CarbonInterface $completedAt): void
    {
        if ($odometerReading === null) {
            return;
        }

        $vehicle = $workOrder->vehicle()->firstOrFail();

        $this->odometerService->record(
            vehicle: $vehicle,
            reading: $odometerReading,
            source: 'maintenance',
            sourceReferenceId: $workOrder->id,
            recordedAt: $completedAt,
            driverId: null,
            notes: 'Captured from work order completion.',
        );
    }

    private function releaseVehicleFromMaintenanceIfSafe(Vehicle $vehicle, int $ignoreWorkOrderId): void
    {
        $hasActiveOrders = WorkOrder::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereKeyNot($ignoreWorkOrderId)
            ->whereIn('status', ['open', 'in_progress'])
            ->exists();

        if ($vehicle->status === 'maintenance' && ! $hasActiveOrders) {
            $vehicle->update(['status' => 'active']);
        }
    }

    private function notifyAssignee(WorkOrder $workOrder, User $actor): void
    {
        if (! $workOrder->assigned_to || $workOrder->assigned_to === $actor->id) {
            return;
        }

        $assignee = User::withoutGlobalScopes()
            ->where('tenant_id', $actor->tenant_id)
            ->whereKey($workOrder->assigned_to)
            ->where('status', 'active')
            ->first();

        if (! $assignee) {
            return;
        }

        $this->notificationService->notifyUser(
            $assignee,
            'work_order_assigned',
            "Work order {$workOrder->work_order_number} assigned",
            'A maintenance work order was assigned to you for execution or follow-up.',
            '/work-orders',
            $workOrder,
            ['priority' => $workOrder->priority]
        );
    }

    private function generateWorkOrderNumber(int $tenantId): string
    {
        $year = now()->format('Y');
        $prefix = config('fleet.work_order.number_prefix', 'WO').'-'.$year.'-';

        $lastNumber = WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('work_order_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('work_order_number');

        $sequence = $lastNumber
            ? ((int) str($lastNumber)->afterLast('-')->toString()) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
