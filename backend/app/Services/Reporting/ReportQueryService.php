<?php

namespace App\Services\Reporting;

use App\Models\ComplianceItem;
use App\Models\Department;
use App\Models\Driver;
use App\Models\FuelLog;
use App\Models\Incident;
use App\Models\MaintenanceRecord;
use App\Models\ReportExport;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\WorkOrder;
use App\Services\Maintenance\ComplianceService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportQueryService
{
    public function __construct(
        private readonly ComplianceService $complianceService,
    ) {
    }

    public function supportData(int $tenantId): array
    {
        return [
            'report_types' => collect(config('fleet.reports.types', []))
                ->map(fn (array $definition, string $key) => [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                ])
                ->values(),
            'vehicles' => Vehicle::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->orderBy('registration_number')
                ->get(['id', 'registration_number', 'make', 'model'])
                ->map(fn (Vehicle $vehicle) => [
                    'id' => $vehicle->id,
                    'label' => $vehicle->registration_number,
                    'secondary' => trim($vehicle->make.' '.$vehicle->model),
                ]),
            'departments' => Department::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Department $department) => [
                    'id' => $department->id,
                    'label' => $department->name,
                    'secondary' => $department->code,
                ]),
            'trip_statuses' => config('fleet.trip.statuses', []),
            'work_order_statuses' => config('fleet.work_order.statuses', []),
            'compliance_categories' => config('fleet.compliance_item.categories', []),
            'compliance_statuses' => config('fleet.compliance_item.statuses', []),
            'incident_statuses' => config('fleet.incident.statuses', []),
            'incident_severities' => config('fleet.incident.severities', []),
            'export_formats' => config('fleet.reports.export_formats', ['csv']),
        ];
    }

    public function dataset(
        string $reportType,
        int $tenantId,
        array $input,
        int $page = 1,
        int $perPage = 15,
    ): array {
        $filters = $this->normalizeFilters($input);
        $report = $this->buildReport($reportType, $tenantId, $filters);
        $rows = collect($report['rows']);
        $total = $rows->count();
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));

        $report['rows'] = $rows->forPage($page, $perPage)->values()->all();
        $report['filters'] = $this->serializeFilters($filters);
        $report['export_formats'] = config('fleet.reports.export_formats', ['csv']);
        $report['meta'] = [
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'per_page' => $perPage,
            'total' => $total,
        ];

        return $report;
    }

    public function exportDataset(string $reportType, int $tenantId, array $input): array
    {
        $filters = $this->normalizeFilters($input);
        $report = $this->buildReport($reportType, $tenantId, $filters);
        $maxRows = (int) config('fleet.reports.max_export_rows', 5000);

        $report['rows'] = collect($report['rows'])->take($maxRows)->values()->all();
        $report['filters'] = $this->serializeFilters($filters);

        return $report;
    }

    private function buildReport(string $reportType, int $tenantId, array $filters): array
    {
        $this->complianceService->refreshStatuses($tenantId);

        return match ($reportType) {
            'fleet-overview' => $this->fleetOverviewReport($tenantId, $filters),
            'vehicle-utilization' => $this->vehicleUtilizationReport($tenantId, $filters),
            'fuel-consumption' => $this->fuelConsumptionReport($tenantId, $filters),
            'maintenance-cost' => $this->maintenanceCostReport($tenantId, $filters),
            'compliance-status' => $this->complianceStatusReport($tenantId, $filters),
            'incident-summary' => $this->incidentSummaryReport($tenantId, $filters),
            default => abort(404, 'Unsupported report type.'),
        };
    }

    private function fleetOverviewReport(int $tenantId, array $filters): array
    {
        $vehicles = $this->vehicleBaseQuery($tenantId, $filters)
            ->get();
        $vehicleIds = $vehicles->pluck('id');

        $activeAssignments = VehicleAssignment::withoutGlobalScopes()
            ->with(['driver', 'department'])
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('vehicle_id', $vehicleIds)
            ->where('status', 'active')
            ->get()
            ->keyBy('vehicle_id');

        $openWorkOrders = WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereIn('status', ['open', 'in_progress'])
            ->get()
            ->groupBy('vehicle_id')
            ->map->count();

        $latestTrips = Trip::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('vehicle_id', $vehicleIds)
            ->orderByDesc('scheduled_start')
            ->get()
            ->groupBy('vehicle_id')
            ->map(fn (Collection $group) => optional($group->first()->scheduled_start)->toDateString());

        $dueCompliance = ComplianceItem::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('compliant_type', Vehicle::class)
            ->whereIn('compliant_id', $vehicleIds)
            ->whereIn('status', ['expiring_soon', 'expired'])
            ->get()
            ->groupBy('compliant_id')
            ->map->count();

        $rows = $vehicles->map(function (Vehicle $vehicle) use ($activeAssignments, $openWorkOrders, $latestTrips, $dueCompliance) {
            $assignment = $activeAssignments->get($vehicle->id);
            $assignmentLabel = match ($assignment?->assignment_type) {
                'driver' => $assignment?->driver?->name,
                'department' => $assignment?->department?->name,
                'pool' => 'Pool allocation',
                default => 'Unassigned',
            };

            return [
                'registration_number' => $vehicle->registration_number,
                'asset_profile' => trim($vehicle->make.' '.$vehicle->model),
                'vehicle_type' => $vehicle->type?->name ?? '—',
                'department' => $vehicle->department?->name ?? '—',
                'status' => $vehicle->status,
                'odometer_reading' => $vehicle->odometer_reading,
                'active_assignment' => $assignmentLabel ?: 'Unassigned',
                'open_work_orders' => (int) ($openWorkOrders->get($vehicle->id) ?? 0),
                'due_compliance_items' => (int) ($dueCompliance->get($vehicle->id) ?? 0),
                'last_trip_date' => $latestTrips->get($vehicle->id) ?? '—',
            ];
        })->values();

        return [
            'type' => 'fleet-overview',
            'title' => config('fleet.reports.types.fleet-overview.label'),
            'description' => config('fleet.reports.types.fleet-overview.description'),
            'summary_metrics' => [
                $this->metric('Tracked vehicles', $vehicles->count(), 'default'),
                $this->metric('Active vehicles', $vehicles->where('status', 'active')->count(), 'success'),
                $this->metric('In maintenance', $vehicles->where('status', 'maintenance')->count(), 'warning'),
                $this->metric('Open work orders', $rows->sum('open_work_orders'), 'info'),
            ],
            'columns' => [
                ['key' => 'registration_number', 'label' => 'Registration'],
                ['key' => 'asset_profile', 'label' => 'Asset'],
                ['key' => 'vehicle_type', 'label' => 'Type'],
                ['key' => 'department', 'label' => 'Department'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'odometer_reading', 'label' => 'Odometer'],
                ['key' => 'active_assignment', 'label' => 'Active assignment'],
                ['key' => 'open_work_orders', 'label' => 'Open work orders'],
                ['key' => 'due_compliance_items', 'label' => 'Due compliance'],
                ['key' => 'last_trip_date', 'label' => 'Last trip'],
            ],
            'rows' => $rows->all(),
        ];
    }

    private function vehicleUtilizationReport(int $tenantId, array $filters): array
    {
        $trips = $this->tripBaseQuery($tenantId, $filters)
            ->with('vehicle')
            ->get();
        $grouped = $trips->groupBy('vehicle_id');

        $rows = $grouped->map(function (Collection $vehicleTrips) {
            $vehicle = $vehicleTrips->first()?->vehicle;
            $completedTrips = $vehicleTrips->where('status', 'completed');
            $distance = (float) $completedTrips->sum(fn (Trip $trip) => (float) ($trip->distance_km ?? 0));
            $latestTrip = $vehicleTrips
                ->sortByDesc(fn (Trip $trip) => $trip->actual_end ?? $trip->scheduled_end ?? $trip->scheduled_start)
                ->first();
            $latestTripDate = $latestTrip?->actual_end ?? $latestTrip?->scheduled_end ?? $latestTrip?->scheduled_start;

            return [
                'registration_number' => $vehicle?->registration_number ?? 'Unknown vehicle',
                'asset_profile' => trim(($vehicle?->make ?? '').' '.($vehicle?->model ?? '')),
                'trip_count' => $vehicleTrips->count(),
                'completed_trips' => $completedTrips->count(),
                'total_distance_km' => round($distance, 2),
                'average_distance_km' => $completedTrips->count() > 0 ? round($distance / $completedTrips->count(), 2) : 0,
                'passenger_volume' => (int) $vehicleTrips->sum(fn (Trip $trip) => (int) ($trip->passengers ?? 0)),
                'last_trip_at' => $latestTripDate?->toDateString() ?? '—',
            ];
        })->sortByDesc('total_distance_km')->values();

        return [
            'type' => 'vehicle-utilization',
            'title' => config('fleet.reports.types.vehicle-utilization.label'),
            'description' => config('fleet.reports.types.vehicle-utilization.description'),
            'summary_metrics' => [
                $this->metric('Vehicles with trips', $rows->count(), 'default'),
                $this->metric('Trips in period', $trips->count(), 'info'),
                $this->metric('Completed distance (km)', round($rows->sum('total_distance_km'), 2), 'success'),
                $this->metric('Average per completed trip', $rows->sum('completed_trips') > 0 ? round($rows->sum('total_distance_km') / $rows->sum('completed_trips'), 2) : 0, 'warning'),
            ],
            'columns' => [
                ['key' => 'registration_number', 'label' => 'Registration'],
                ['key' => 'asset_profile', 'label' => 'Asset'],
                ['key' => 'trip_count', 'label' => 'Trips'],
                ['key' => 'completed_trips', 'label' => 'Completed'],
                ['key' => 'total_distance_km', 'label' => 'Distance (km)'],
                ['key' => 'average_distance_km', 'label' => 'Avg distance'],
                ['key' => 'passenger_volume', 'label' => 'Passengers'],
                ['key' => 'last_trip_at', 'label' => 'Last trip'],
            ],
            'rows' => $rows->all(),
        ];
    }

    private function fuelConsumptionReport(int $tenantId, array $filters): array
    {
        $fuelLogs = $this->fuelLogBaseQuery($tenantId, $filters)
            ->with('vehicle')
            ->get();
        $grouped = $fuelLogs->groupBy('vehicle_id');

        $rows = $grouped->map(function (Collection $logs) {
            $vehicle = $logs->first()?->vehicle;
            $totalLiters = (float) $logs->sum(fn (FuelLog $log) => (float) $log->quantity_liters);
            $totalCost = (float) $logs->sum(fn (FuelLog $log) => (float) $log->total_cost);

            return [
                'registration_number' => $vehicle?->registration_number ?? 'Unknown vehicle',
                'asset_profile' => trim(($vehicle?->make ?? '').' '.($vehicle?->model ?? '')),
                'fuel_entries' => $logs->count(),
                'total_liters' => round($totalLiters, 2),
                'total_cost' => round($totalCost, 2),
                'average_cost_per_liter' => $totalLiters > 0 ? round($totalCost / $totalLiters, 4) : 0,
                'full_tank_events' => $logs->where('is_full_tank', true)->count(),
                'last_fueled_at' => optional($logs->sortByDesc('fueled_at')->first()?->fueled_at)?->toDateString() ?? '—',
            ];
        })->sortByDesc('total_cost')->values();

        $totalLiters = (float) $rows->sum('total_liters');
        $totalCost = (float) $rows->sum('total_cost');

        return [
            'type' => 'fuel-consumption',
            'title' => config('fleet.reports.types.fuel-consumption.label'),
            'description' => config('fleet.reports.types.fuel-consumption.description'),
            'summary_metrics' => [
                $this->metric('Refuel events', $fuelLogs->count(), 'default'),
                $this->metric('Liters dispensed', round($totalLiters, 2), 'info'),
                $this->metric('Fuel spend', round($totalCost, 2), 'warning'),
                $this->metric('Average cost per liter', $totalLiters > 0 ? round($totalCost / $totalLiters, 4) : 0, 'success'),
            ],
            'columns' => [
                ['key' => 'registration_number', 'label' => 'Registration'],
                ['key' => 'asset_profile', 'label' => 'Asset'],
                ['key' => 'fuel_entries', 'label' => 'Entries'],
                ['key' => 'total_liters', 'label' => 'Liters'],
                ['key' => 'total_cost', 'label' => 'Total cost'],
                ['key' => 'average_cost_per_liter', 'label' => 'Avg cost/liter'],
                ['key' => 'full_tank_events', 'label' => 'Full tank events'],
                ['key' => 'last_fueled_at', 'label' => 'Last fuel date'],
            ],
            'rows' => $rows->all(),
        ];
    }

    private function maintenanceCostReport(int $tenantId, array $filters): array
    {
        $records = $this->maintenanceRecordBaseQuery($tenantId, $filters)
            ->with('vehicle')
            ->get();
        $grouped = $records->groupBy('vehicle_id');

        $rows = $grouped->map(function (Collection $vehicleRecords) {
            $vehicle = $vehicleRecords->first()?->vehicle;
            $totalCost = (float) $vehicleRecords->sum(fn (MaintenanceRecord $record) => (float) $record->total_cost);
            $laborCost = (float) $vehicleRecords->sum(fn (MaintenanceRecord $record) => (float) $record->labor_cost);
            $partsCost = (float) $vehicleRecords->sum(fn (MaintenanceRecord $record) => (float) $record->parts_cost);
            $downtime = (float) $vehicleRecords->sum(fn (MaintenanceRecord $record) => (float) $record->downtime_hours);

            return [
                'registration_number' => $vehicle?->registration_number ?? 'Unknown vehicle',
                'asset_profile' => trim(($vehicle?->make ?? '').' '.($vehicle?->model ?? '')),
                'maintenance_events' => $vehicleRecords->count(),
                'total_cost' => round($totalCost, 2),
                'labor_cost' => round($laborCost, 2),
                'parts_cost' => round($partsCost, 2),
                'downtime_hours' => round($downtime, 2),
                'last_completed_at' => optional($vehicleRecords->sortByDesc('completed_at')->first()?->completed_at)?->toDateString() ?? '—',
            ];
        })->sortByDesc('total_cost')->values();

        $events = max(1, $rows->sum('maintenance_events'));

        return [
            'type' => 'maintenance-cost',
            'title' => config('fleet.reports.types.maintenance-cost.label'),
            'description' => config('fleet.reports.types.maintenance-cost.description'),
            'summary_metrics' => [
                $this->metric('Maintenance records', $records->count(), 'default'),
                $this->metric('Total cost', round($rows->sum('total_cost'), 2), 'warning'),
                $this->metric('Downtime hours', round($rows->sum('downtime_hours'), 2), 'danger'),
                $this->metric('Average cost per event', round($rows->sum('total_cost') / $events, 2), 'info'),
            ],
            'columns' => [
                ['key' => 'registration_number', 'label' => 'Registration'],
                ['key' => 'asset_profile', 'label' => 'Asset'],
                ['key' => 'maintenance_events', 'label' => 'Events'],
                ['key' => 'total_cost', 'label' => 'Total cost'],
                ['key' => 'labor_cost', 'label' => 'Labor cost'],
                ['key' => 'parts_cost', 'label' => 'Parts cost'],
                ['key' => 'downtime_hours', 'label' => 'Downtime hours'],
                ['key' => 'last_completed_at', 'label' => 'Last completed'],
            ],
            'rows' => $rows->all(),
        ];
    }

    private function complianceStatusReport(int $tenantId, array $filters): array
    {
        $items = ComplianceItem::withoutGlobalScopes()
            ->with('compliant')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->when($filters['category'], fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'], function (Builder $query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $searchQuery) use ($like) {
                    $searchQuery
                        ->where('title', 'like', $like)
                        ->orWhere('reference_number', 'like', $like)
                        ->orWhere('issuer', 'like', $like);
                });
            })
            ->get()
            ->filter(function (ComplianceItem $item) use ($filters, $tenantId) {
                if ($filters['vehicle_id']) {
                    if ($item->compliant_type !== Vehicle::class || $item->compliant_id !== $filters['vehicle_id']) {
                        return false;
                    }
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

                if ($item->compliant_type === Driver::class) {
                    $driver = Driver::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->find($item->compliant_id);

                    return $driver?->department_id === $filters['department_id'];
                }

                return true;
            })
            ->filter(function (ComplianceItem $item) use ($filters) {
                if (! $item->expiry_date) {
                    return true;
                }

                return $item->expiry_date->betweenIncluded($filters['date_from']->toDateString(), $filters['date_to']->toDateString());
            })
            ->map(function (ComplianceItem $item) {
                $entityLabel = match ($item->compliant_type) {
                    Vehicle::class => $item->compliant?->registration_number ?? 'Vehicle',
                    Driver::class => $item->compliant?->name ?? 'Driver',
                    default => 'Entity',
                };

                $daysUntilExpiry = $item->expiry_date
                    ? now()->startOfDay()->diffInDays($item->expiry_date->startOfDay(), false)
                    : null;

                return [
                    'title' => $item->title,
                    'category' => $item->category,
                    'entity' => $entityLabel,
                    'reference_number' => $item->reference_number ?? '—',
                    'issuer' => $item->issuer ?? '—',
                    'expiry_date' => $item->expiry_date?->toDateString() ?? '—',
                    'days_until_expiry' => $daysUntilExpiry,
                    'status' => $item->status,
                ];
            })
            ->sortBy('expiry_date')
            ->values();

        return [
            'type' => 'compliance-status',
            'title' => config('fleet.reports.types.compliance-status.label'),
            'description' => config('fleet.reports.types.compliance-status.description'),
            'summary_metrics' => [
                $this->metric('Tracked items', $items->count(), 'default'),
                $this->metric('Expired', $items->where('status', 'expired')->count(), 'danger'),
                $this->metric('Expiring soon', $items->where('status', 'expiring_soon')->count(), 'warning'),
                $this->metric('Valid', $items->where('status', 'valid')->count(), 'success'),
            ],
            'columns' => [
                ['key' => 'title', 'label' => 'Requirement'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'entity', 'label' => 'Entity'],
                ['key' => 'reference_number', 'label' => 'Reference'],
                ['key' => 'issuer', 'label' => 'Issuer'],
                ['key' => 'expiry_date', 'label' => 'Expiry date'],
                ['key' => 'days_until_expiry', 'label' => 'Days to expiry'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $items->all(),
        ];
    }

    private function incidentSummaryReport(int $tenantId, array $filters): array
    {
        $incidents = $this->incidentBaseQuery($tenantId, $filters)
            ->with(['vehicle', 'driver'])
            ->get();

        $rows = $incidents->map(fn (Incident $incident) => [
            'incident_number' => $incident->incident_number,
            'occurred_at' => optional($incident->occurred_at)?->toDateTimeString() ?? '—',
            'incident_type' => $incident->incident_type,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'vehicle' => $incident->vehicle?->registration_number ?? '—',
            'driver' => $incident->driver?->name ?? '—',
            'location' => $incident->location ?? '—',
            'estimated_cost' => (float) ($incident->estimated_cost ?? 0),
        ])->sortByDesc('occurred_at')->values();

        return [
            'type' => 'incident-summary',
            'title' => config('fleet.reports.types.incident-summary.label'),
            'description' => config('fleet.reports.types.incident-summary.description'),
            'summary_metrics' => [
                $this->metric('Incidents', $incidents->count(), 'default'),
                $this->metric('Critical', $incidents->where('severity', 'critical')->count(), 'danger'),
                $this->metric('Open workflow', $incidents->whereIn('status', ['reported', 'under_review', 'action_required'])->count(), 'warning'),
                $this->metric('Estimated exposure', round($rows->sum('estimated_cost'), 2), 'info'),
            ],
            'columns' => [
                ['key' => 'incident_number', 'label' => 'Incident'],
                ['key' => 'occurred_at', 'label' => 'Occurred at'],
                ['key' => 'incident_type', 'label' => 'Type'],
                ['key' => 'severity', 'label' => 'Severity'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'vehicle', 'label' => 'Vehicle'],
                ['key' => 'driver', 'label' => 'Driver'],
                ['key' => 'location', 'label' => 'Location'],
                ['key' => 'estimated_cost', 'label' => 'Estimated cost'],
            ],
            'rows' => $rows->all(),
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
            'status' => isset($payload['status']) && $payload['status'] !== '' ? (string) $payload['status'] : null,
            'category' => isset($payload['category']) && $payload['category'] !== '' ? (string) $payload['category'] : null,
            'severity' => isset($payload['severity']) && $payload['severity'] !== '' ? (string) $payload['severity'] : null,
        ];
    }

    private function serializeFilters(array $filters): array
    {
        return [
            'search' => $filters['search'],
            'date_from' => $filters['date_from']->toDateString(),
            'date_to' => $filters['date_to']->toDateString(),
            'vehicle_id' => $filters['vehicle_id'],
            'department_id' => $filters['department_id'],
            'status' => $filters['status'],
            'category' => $filters['category'],
            'severity' => $filters['severity'],
        ];
    }

    private function vehicleBaseQuery(int $tenantId, array $filters): Builder
    {
        return Vehicle::withoutGlobalScopes()
            ->with(['type', 'department'])
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->when($filters['vehicle_id'], fn (Builder $query, int $vehicleId) => $query->whereKey($vehicleId))
            ->when($filters['department_id'], fn (Builder $query, int $departmentId) => $query->where('department_id', $departmentId))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'], function (Builder $query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $searchQuery) use ($like) {
                    $searchQuery
                        ->where('registration_number', 'like', $like)
                        ->orWhere('make', 'like', $like)
                        ->orWhere('model', 'like', $like)
                        ->orWhere('asset_tag', 'like', $like);
                });
            })
            ->orderBy('registration_number');
    }

    private function tripBaseQuery(int $tenantId, array $filters): Builder
    {
        return Trip::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('scheduled_start', [$filters['date_from'], $filters['date_to']])
            ->when($filters['vehicle_id'], fn (Builder $query, int $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['department_id'], fn (Builder $query, int $departmentId) => $query->whereHas('vehicle', fn (Builder $vehicleQuery) => $vehicleQuery->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('department_id', $departmentId)))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'], function (Builder $query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $searchQuery) use ($like) {
                    $searchQuery
                        ->where('trip_number', 'like', $like)
                        ->orWhere('purpose', 'like', $like)
                        ->orWhere('origin', 'like', $like)
                        ->orWhere('destination', 'like', $like);
                });
            });
    }

    private function fuelLogBaseQuery(int $tenantId, array $filters): Builder
    {
        return FuelLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('fueled_at', [$filters['date_from'], $filters['date_to']])
            ->when($filters['vehicle_id'], fn (Builder $query, int $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['department_id'], fn (Builder $query, int $departmentId) => $query->whereHas('vehicle', fn (Builder $vehicleQuery) => $vehicleQuery->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('department_id', $departmentId)))
            ->when($filters['search'], function (Builder $query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $searchQuery) use ($like) {
                    $searchQuery
                        ->where('reference_number', 'like', $like)
                        ->orWhere('fuel_type', 'like', $like);
                });
            });
    }

    private function maintenanceRecordBaseQuery(int $tenantId, array $filters): Builder
    {
        return MaintenanceRecord::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('completed_at', [$filters['date_from'], $filters['date_to']])
            ->when($filters['vehicle_id'], fn (Builder $query, int $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['department_id'], fn (Builder $query, int $departmentId) => $query->whereHas('vehicle', fn (Builder $vehicleQuery) => $vehicleQuery->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('department_id', $departmentId)))
            ->when($filters['search'], fn (Builder $query, string $search) => $query->where('summary', 'like', '%'.$search.'%'));
    }

    private function incidentBaseQuery(int $tenantId, array $filters): Builder
    {
        return Incident::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('occurred_at', [$filters['date_from'], $filters['date_to']])
            ->when($filters['vehicle_id'], fn (Builder $query, int $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['department_id'], fn (Builder $query, int $departmentId) => $query->whereHas('vehicle', fn (Builder $vehicleQuery) => $vehicleQuery->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('department_id', $departmentId)))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['severity'], fn (Builder $query, string $severity) => $query->where('severity', $severity))
            ->when($filters['search'], function (Builder $query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $searchQuery) use ($like) {
                    $searchQuery
                        ->where('incident_number', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('location', 'like', $like);
                });
            });
    }

    private function metric(string $label, float|int|string $value, string $tone = 'default'): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'tone' => $tone,
        ];
    }
}
