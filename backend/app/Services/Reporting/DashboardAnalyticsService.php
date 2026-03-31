<?php

namespace App\Services\Reporting;

use App\Models\ComplianceItem;
use App\Models\Department;
use App\Models\FuelLog;
use App\Models\Incident;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleComponent;
use App\Models\WorkOrder;
use App\Services\Maintenance\ComplianceService;
use App\Services\Maintenance\MaintenanceService;
use App\Services\Maintenance\VehicleComponentService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardAnalyticsService
{
    public function __construct(
        private readonly ComplianceService $complianceService,
        private readonly MaintenanceService $maintenanceService,
        private readonly VehicleComponentService $vehicleComponentService,
    ) {
    }

    public function dashboard(int $tenantId, array $input): array
    {
        $filters = $this->normalizeFilters($input);

        $this->complianceService->refreshStatuses($tenantId);
        $this->vehicleComponentService->refreshStatuses($tenantId);

        $vehicles = $this->vehicleBaseQuery($tenantId, $filters)->get();
        $vehicleIds = $vehicles->pluck('id');
        $trips = $this->tripBaseQuery($tenantId, $filters)->get();
        $fuelLogs = $this->fuelLogBaseQuery($tenantId, $filters)->get();
        $maintenanceRecords = $this->maintenanceRecordBaseQuery($tenantId, $filters)->get();
        $incidents = $this->incidentBaseQuery($tenantId, $filters)->get();

        $openWorkOrders = WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->when($filters['vehicle_id'], fn (Builder $query, int $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['department_id'], fn (Builder $query, int $departmentId) => $query->whereHas('vehicle', fn (Builder $vehicleQuery) => $vehicleQuery->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('department_id', $departmentId)))
            ->whereIn('status', ['open', 'in_progress'])
            ->get();

        $maintenanceSchedules = MaintenanceSchedule::withoutGlobalScopes()
            ->with('vehicle')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->when($filters['vehicle_id'], fn (Builder $query, int $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['department_id'], fn (Builder $query, int $departmentId) => $query->whereHas('vehicle', fn (Builder $vehicleQuery) => $vehicleQuery->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('department_id', $departmentId)))
            ->where('status', 'active')
            ->get();

        $components = VehicleComponent::withoutGlobalScopes()
            ->with('vehicle')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->when($filters['vehicle_id'], fn (Builder $query, int $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['department_id'], fn (Builder $query, int $departmentId) => $query->whereHas('vehicle', fn (Builder $vehicleQuery) => $vehicleQuery->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('department_id', $departmentId)))
            ->whereNotIn('status', ['retired', 'failed'])
            ->get();

        $complianceItems = ComplianceItem::withoutGlobalScopes()
            ->with('compliant')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get()
            ->filter(fn (ComplianceItem $item) => $this->complianceMatchesFilters($item, $tenantId, $filters))
            ->values();

        $dueSoonSchedules = $maintenanceSchedules->filter(fn (MaintenanceSchedule $schedule) => ! $this->maintenanceService->isScheduleOverdue($schedule) && $this->maintenanceService->isScheduleUpcoming($schedule))->values();
        $overdueSchedules = $maintenanceSchedules->filter(fn (MaintenanceSchedule $schedule) => $this->maintenanceService->isScheduleOverdue($schedule))->values();
        $dueSoonComponents = $components->filter(fn (VehicleComponent $component) => ! $this->vehicleComponentService->isOverdue($component) && $this->vehicleComponentService->isDueSoon($component))->values();
        $overdueComponents = $components->filter(fn (VehicleComponent $component) => $this->vehicleComponentService->isOverdue($component))->values();

        $monthlyBuckets = $this->monthBuckets($filters['date_from'], $filters['date_to']);
        $fuelTrend = $this->sumByMonth($fuelLogs, 'fueled_at', fn (FuelLog $log) => (float) $log->total_cost, $monthlyBuckets);
        $maintenanceTrend = $this->sumByMonth($maintenanceRecords, 'completed_at', fn (MaintenanceRecord $record) => (float) $record->total_cost, $monthlyBuckets);
        $incidentTrend = $this->countByMonth($incidents, 'occurred_at', $monthlyBuckets);
        $criticalIncidentTrend = $this->countByMonth($incidents->where('severity', 'critical'), 'occurred_at', $monthlyBuckets);

        $utilization = $trips->groupBy('vehicle_id')
            ->map(function (Collection $vehicleTrips) {
                $vehicle = $vehicleTrips->first()?->vehicle;
                $distance = (float) $vehicleTrips->where('status', 'completed')->sum(fn (Trip $trip) => (float) ($trip->distance_km ?? 0));

                return [
                    'label' => $vehicle?->registration_number ?? 'Unknown vehicle',
                    'distance_km' => round($distance, 2),
                    'trip_count' => $vehicleTrips->count(),
                ];
            })
            ->sortByDesc('distance_km')
            ->take(6)
            ->values();

        $urgentCompliance = $complianceItems
            ->whereIn('status', ['expired', 'expiring_soon'])
            ->sortBy(fn (ComplianceItem $item) => $item->expiry_date?->getTimestamp() ?? PHP_INT_MAX)
            ->take(5)
            ->map(function (ComplianceItem $item) {
                $entity = match ($item->compliant_type) {
                    Vehicle::class => $item->compliant?->registration_number ?? 'Vehicle',
                    default => $item->compliant?->name ?? 'Entity',
                };

                return [
                    'title' => $item->title,
                    'entity' => $entity,
                    'status' => $item->status,
                    'expiry_date' => $item->expiry_date?->toDateString(),
                ];
            })
            ->values();

        $urgentMaintenance = collect()
            ->merge($overdueSchedules->map(fn (MaintenanceSchedule $schedule) => [
                'title' => $schedule->title,
                'asset' => $schedule->vehicle?->registration_number ?? 'Vehicle',
                'status' => 'overdue',
                'source' => 'schedule',
            ]))
            ->merge($overdueComponents->map(fn (VehicleComponent $component) => [
                'title' => trim(implode(' ', array_filter([$component->brand, $component->model]))) ?: $component->component_type,
                'asset' => $component->vehicle?->registration_number ?? 'Vehicle',
                'status' => 'due_replacement',
                'source' => 'component',
            ]))
            ->take(6)
            ->values();

        return [
            'filters' => [
                'date_from' => $filters['date_from']->toDateString(),
                'date_to' => $filters['date_to']->toDateString(),
                'vehicle_id' => $filters['vehicle_id'],
                'department_id' => $filters['department_id'],
            ],
            'metrics' => [
                $this->metric('Active fleet assets', $vehicles->where('status', 'active')->count(), 'default', 'Currently active vehicles in the filtered fleet scope.'),
                $this->metric('Trips in period', $trips->count(), 'info', 'Trips scheduled in the selected reporting window.'),
                $this->metric('Fuel spend', round($fuelLogs->sum(fn (FuelLog $log) => (float) $log->total_cost), 2), 'warning', 'Fuel cost accumulated in the selected reporting period.'),
                $this->metric('Maintenance cost', round($maintenanceRecords->sum(fn (MaintenanceRecord $record) => (float) $record->total_cost), 2), 'danger', 'Completed maintenance cost recorded in the reporting window.'),
                $this->metric('Open work orders', $openWorkOrders->count(), 'info', 'Maintenance jobs still awaiting completion or cancellation.'),
                $this->metric('Due compliance items', $complianceItems->whereIn('status', ['expiring_soon', 'expired'])->count(), 'warning', 'Compliance records requiring attention soon or already overdue.'),
                $this->metric('Due components', $dueSoonComponents->count() + $overdueComponents->count(), 'warning', 'Components nearing or exceeding replacement thresholds.'),
                $this->metric('Critical incidents', $incidents->where('severity', 'critical')->count(), 'danger', 'Critical incidents captured in the selected reporting period.'),
            ],
            'charts' => [
                'fleet_status' => [
                    'type' => 'pie',
                    'items' => collect(config('fleet.vehicle.statuses', []))
                        ->map(fn (string $status) => [
                            'name' => str_replace('_', ' ', ucfirst($status)),
                            'value' => $vehicles->where('status', $status)->count(),
                        ])
                        ->filter(fn (array $item) => $item['value'] > 0)
                        ->values(),
                ],
                'trip_status' => [
                    'type' => 'bar',
                    'categories' => collect(config('fleet.trip.statuses', []))->map(fn (string $status) => str_replace('_', ' ', ucfirst($status)))->values(),
                    'series' => [
                        [
                            'name' => 'Trips',
                            'type' => 'bar',
                            'data' => collect(config('fleet.trip.statuses', []))->map(fn (string $status) => $trips->where('status', $status)->count())->values(),
                        ],
                    ],
                ],
                'cost_trend' => [
                    'type' => 'mixed',
                    'categories' => $monthlyBuckets->pluck('label')->values(),
                    'series' => [
                        ['name' => 'Fuel cost', 'type' => 'bar', 'data' => $fuelTrend],
                        ['name' => 'Maintenance cost', 'type' => 'line', 'data' => $maintenanceTrend],
                    ],
                ],
                'utilization_top' => [
                    'type' => 'bar',
                    'categories' => $utilization->pluck('label')->values(),
                    'series' => [
                        ['name' => 'Distance (km)', 'type' => 'bar', 'data' => $utilization->pluck('distance_km')->values()],
                        ['name' => 'Trips', 'type' => 'line', 'data' => $utilization->pluck('trip_count')->values()],
                    ],
                ],
                'compliance_status' => [
                    'type' => 'pie',
                    'items' => $complianceItems->groupBy('status')
                        ->map(fn (Collection $items, string $status) => [
                            'name' => str_replace('_', ' ', ucfirst($status)),
                            'value' => $items->count(),
                        ])
                        ->values(),
                ],
                'incident_trend' => [
                    'type' => 'mixed',
                    'categories' => $monthlyBuckets->pluck('label')->values(),
                    'series' => [
                        ['name' => 'Incidents', 'type' => 'bar', 'data' => $incidentTrend],
                        ['name' => 'Critical', 'type' => 'line', 'data' => $criticalIncidentTrend],
                    ],
                ],
            ],
            'highlights' => [
                'top_utilization_vehicles' => $utilization->all(),
                'urgent_compliance_items' => $urgentCompliance->all(),
                'urgent_maintenance_items' => $urgentMaintenance->all(),
                'maintenance_health' => [
                    'due_soon_schedules' => $dueSoonSchedules->count(),
                    'overdue_schedules' => $overdueSchedules->count(),
                    'due_soon_components' => $dueSoonComponents->count(),
                    'overdue_components' => $overdueComponents->count(),
                ],
            ],
        ];
    }

    private function normalizeFilters(array $input): array
    {
        $payload = $input['filter'] ?? $input;
        $defaultWindow = (int) config('fleet.reports.default_day_window', 30);
        $dateFrom = isset($payload['date_from'])
            ? CarbonImmutable::parse($payload['date_from'])->startOfDay()
            : CarbonImmutable::now()->subDays($defaultWindow - 1)->startOfDay();
        $dateTo = isset($payload['date_to'])
            ? CarbonImmutable::parse($payload['date_to'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        if ($dateTo->lessThan($dateFrom)) {
            [$dateFrom, $dateTo] = [$dateTo->startOfDay(), $dateFrom->endOfDay()];
        }

        return [
            'search' => trim((string) ($input['search'] ?? '')),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'vehicle_id' => isset($payload['vehicle_id']) && $payload['vehicle_id'] !== '' ? (int) $payload['vehicle_id'] : null,
            'department_id' => isset($payload['department_id']) && $payload['department_id'] !== '' ? (int) $payload['department_id'] : null,
        ];
    }

    private function vehicleBaseQuery(int $tenantId, array $filters): Builder
    {
        return Vehicle::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->when($filters['vehicle_id'], fn (Builder $query, int $vehicleId) => $query->whereKey($vehicleId))
            ->when($filters['department_id'], fn (Builder $query, int $departmentId) => $query->where('department_id', $departmentId));
    }

    private function tripBaseQuery(int $tenantId, array $filters): Builder
    {
        return Trip::withoutGlobalScopes()
            ->with('vehicle')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('scheduled_start', [$filters['date_from'], $filters['date_to']])
            ->when($filters['vehicle_id'], fn (Builder $query, int $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['department_id'], fn (Builder $query, int $departmentId) => $query->whereHas('vehicle', fn (Builder $vehicleQuery) => $vehicleQuery->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('department_id', $departmentId)));
    }

    private function fuelLogBaseQuery(int $tenantId, array $filters): Builder
    {
        return FuelLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('fueled_at', [$filters['date_from'], $filters['date_to']])
            ->when($filters['vehicle_id'], fn (Builder $query, int $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['department_id'], fn (Builder $query, int $departmentId) => $query->whereHas('vehicle', fn (Builder $vehicleQuery) => $vehicleQuery->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('department_id', $departmentId)));
    }

    private function maintenanceRecordBaseQuery(int $tenantId, array $filters): Builder
    {
        return MaintenanceRecord::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('completed_at', [$filters['date_from'], $filters['date_to']])
            ->when($filters['vehicle_id'], fn (Builder $query, int $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['department_id'], fn (Builder $query, int $departmentId) => $query->whereHas('vehicle', fn (Builder $vehicleQuery) => $vehicleQuery->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('department_id', $departmentId)));
    }

    private function incidentBaseQuery(int $tenantId, array $filters): Builder
    {
        return Incident::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('occurred_at', [$filters['date_from'], $filters['date_to']])
            ->when($filters['vehicle_id'], fn (Builder $query, int $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['department_id'], fn (Builder $query, int $departmentId) => $query->whereHas('vehicle', fn (Builder $vehicleQuery) => $vehicleQuery->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('department_id', $departmentId)));
    }

    private function complianceMatchesFilters(ComplianceItem $item, int $tenantId, array $filters): bool
    {
        if ($filters['vehicle_id'] && ($item->compliant_type !== Vehicle::class || $item->compliant_id !== $filters['vehicle_id'])) {
            return false;
        }

        if ($item->expiry_date && ! $item->expiry_date->betweenIncluded($filters['date_from']->toDateString(), $filters['date_to']->toDateString())) {
            return false;
        }

        if ($filters['department_id'] === null) {
            return true;
        }

        if ($item->compliant_type === Vehicle::class) {
            $vehicle = Vehicle::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->find($item->compliant_id);

            return $vehicle?->department_id === $filters['department_id'];
        }

        return true;
    }

    private function monthBuckets(CarbonImmutable $dateFrom, CarbonImmutable $dateTo): Collection
    {
        $cursor = $dateFrom->startOfMonth();
        $end = $dateTo->startOfMonth();
        $buckets = collect();

        while ($cursor->lessThanOrEqualTo($end)) {
            $buckets->push([
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->format('M Y'),
            ]);

            $cursor = $cursor->addMonth();
        }

        return $buckets;
    }

    private function sumByMonth(iterable $items, string $dateField, callable $resolver, Collection $buckets): Collection
    {
        $totals = $buckets->mapWithKeys(fn (array $bucket) => [$bucket['key'] => 0.0]);

        foreach ($items as $item) {
            $date = $item->{$dateField};

            if (! $date) {
                continue;
            }

            $monthKey = $date->format('Y-m');

            if (! $totals->has($monthKey)) {
                continue;
            }

            $totals->put($monthKey, round(((float) $totals->get($monthKey, 0)) + (float) $resolver($item), 2));
        }

        return $buckets->map(fn (array $bucket) => $totals[$bucket['key']] ?? 0.0)->values();
    }

    private function countByMonth(iterable $items, string $dateField, Collection $buckets): Collection
    {
        $totals = $buckets->mapWithKeys(fn (array $bucket) => [$bucket['key'] => 0]);

        foreach ($items as $item) {
            $date = $item->{$dateField};

            if (! $date) {
                continue;
            }

            $monthKey = $date->format('Y-m');

            if (! $totals->has($monthKey)) {
                continue;
            }

            $totals->put($monthKey, ((int) $totals->get($monthKey, 0)) + 1);
        }

        return $buckets->map(fn (array $bucket) => $totals[$bucket['key']] ?? 0)->values();
    }

    private function metric(string $label, float|int|string $value, string $tone = 'default', ?string $hint = null): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'tone' => $tone,
            'hint' => $hint,
        ];
    }
}
