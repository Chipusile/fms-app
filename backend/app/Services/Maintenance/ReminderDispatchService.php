<?php

namespace App\Services\Maintenance;

use App\Enums\TenantStatus;
use App\Models\ComplianceItem;
use App\Models\MaintenanceSchedule;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\VehicleComponent;
use App\Services\Workflow\NotificationService;

class ReminderDispatchService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly VehicleComponentService $vehicleComponentService,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function dispatch(?int $tenantId = null): array
    {
        $totals = [
            'maintenance_due' => 0,
            'compliance_expiring' => 0,
            'component_due_replacement' => 0,
        ];

        $tenants = Tenant::withoutGlobalScopes()
            ->when($tenantId, fn ($query) => $query->whereKey($tenantId))
            ->where('status', TenantStatus::Active->value)
            ->get();

        foreach ($tenants as $tenant) {
            if (! (bool) Setting::getTenantValue($tenant->id, 'notifications.reminders.enabled', true)) {
                continue;
            }

            $totals['maintenance_due'] += $this->dispatchMaintenanceScheduleReminders($tenant->id);
            $totals['compliance_expiring'] += $this->dispatchComplianceReminders($tenant->id);
            $totals['component_due_replacement'] += $this->dispatchComponentReminders($tenant->id);
        }

        return $totals;
    }

    private function dispatchMaintenanceScheduleReminders(int $tenantId): int
    {
        $recipients = $this->notificationService->recipientsWithPermission($tenantId, 'maintenance.update');

        if ($recipients->isEmpty()) {
            return 0;
        }

        $daysWindow = (int) Setting::getTenantValue($tenantId, 'maintenance.reminder_days', 7);
        $kmWindow = (int) Setting::getTenantValue($tenantId, 'maintenance.reminder_km_buffer', 500);

        $schedules = MaintenanceSchedule::withoutGlobalScopes()
            ->with('vehicle')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get()
            ->filter(function (MaintenanceSchedule $schedule) use ($daysWindow, $kmWindow) {
                $vehicleOdometer = $schedule->vehicle?->odometer_reading;
                $overdue = ($schedule->next_due_at && $schedule->next_due_at->endOfDay()->isPast())
                    || ($schedule->next_due_km !== null && $vehicleOdometer !== null && $vehicleOdometer >= $schedule->next_due_km);
                $dueSoon = ($schedule->next_due_at && $schedule->next_due_at->endOfDay()->lessThanOrEqualTo(now()->copy()->addDays($daysWindow)->endOfDay()))
                    || ($schedule->next_due_km !== null && $vehicleOdometer !== null && ($vehicleOdometer + $kmWindow) >= $schedule->next_due_km);

                return $overdue || $dueSoon;
            });

        foreach ($schedules as $schedule) {
            $vehicleLabel = $schedule->vehicle?->registration_number ?? 'fleet asset';
            $this->notificationService->notifyUsers(
                $recipients,
                'maintenance_due',
                'Maintenance schedule due',
                "{$schedule->title} for {$vehicleLabel} is due or approaching its threshold.",
                "/maintenance-schedules/{$schedule->id}/edit",
                $schedule,
                ['vehicle_id' => $schedule->vehicle_id],
            );
        }

        return $schedules->count() * $recipients->count();
    }

    private function dispatchComplianceReminders(int $tenantId): int
    {
        $recipients = $this->notificationService->recipientsWithPermission($tenantId, 'compliance.update');

        if ($recipients->isEmpty()) {
            return 0;
        }

        $window = (int) Setting::getTenantValue($tenantId, 'compliance.reminder_days', 30);
        $cutoff = now()->copy()->addDays($window)->endOfDay();

        $items = ComplianceItem::withoutGlobalScopes()
            ->with('compliant')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $cutoff)
            ->whereNull('deleted_at')
            ->get();

        foreach ($items as $item) {
            $entityLabel = match ($item->compliant_type) {
                config('fleet.compliance_item.compliants.vehicle') => $item->compliant?->registration_number ?? 'vehicle',
                config('fleet.compliance_item.compliants.driver') => $item->compliant?->name ?? 'driver',
                default => 'entity',
            };

            $this->notificationService->notifyUsers(
                $recipients,
                'compliance_expiring',
                'Compliance renewal approaching',
                "{$item->title} for {$entityLabel} expires on {$item->expiry_date?->toDateString()}.",
                "/compliance/{$item->id}/edit",
                $item,
                ['category' => $item->category],
            );
        }

        return $items->count() * $recipients->count();
    }

    private function dispatchComponentReminders(int $tenantId): int
    {
        $recipients = $this->notificationService->recipientsWithPermission($tenantId, 'maintenance.update');

        if ($recipients->isEmpty()) {
            return 0;
        }

        $components = $this->vehicleComponentService->dueSoonComponents($tenantId, 50)
            ->merge($this->vehicleComponentService->overdueComponents($tenantId, 50))
            ->unique('id')
            ->values();

        foreach ($components as $component) {
            $vehicleLabel = $component->vehicle?->registration_number ?? 'vehicle';
            $componentLabel = trim(implode(' ', array_filter([$component->brand, $component->model])));
            $descriptor = $componentLabel !== '' ? $componentLabel : $component->component_type;

            $this->notificationService->notifyUsers(
                $recipients,
                'component_due_replacement',
                'Vehicle component attention required',
                "{$descriptor} on {$vehicleLabel} is due for replacement or close to threshold.",
                "/vehicle-components/{$component->id}/edit",
                $component,
                ['component_type' => $component->component_type],
            );
        }

        return $components->count() * $recipients->count();
    }
}
