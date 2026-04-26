<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\MaintenanceScheduleRequest;
use App\Http\Resources\Api\V1\MaintenanceScheduleResource;
use App\Models\MaintenanceSchedule;
use App\Models\ServiceProvider;
use App\Models\Vehicle;
use App\Services\Maintenance\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceScheduleController extends Controller
{
    public function __construct(
        private readonly MaintenanceService $maintenanceService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MaintenanceSchedule::class);

        $schedules = MaintenanceSchedule::query()
            ->with(['vehicle', 'serviceProvider'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('title', 'ilike', $search)
                        ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('registration_number', 'ilike', $search));
                });
            })
            ->when($request->filled('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->filled('filter.schedule_type'), fn ($query, $type) => $query->where('schedule_type', $type))
            ->when($request->filled('filter.vehicle_id'), fn ($query, $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->orderBy('next_due_at')
            ->orderBy('next_due_km')
            ->paginate($this->perPage($request, 15));

        return ApiResponse::success(
            MaintenanceScheduleResource::collection($schedules),
            meta: [
                'current_page' => $schedules->currentPage(),
                'last_page' => $schedules->lastPage(),
                'per_page' => $schedules->perPage(),
                'total' => $schedules->total(),
            ]
        );
    }

    public function supportData(Request $request): JsonResponse
    {
        if (! $request->user()?->hasAnyPermission(['maintenance.view', 'maintenance.create', 'maintenance.update'])) {
            return ApiResponse::forbidden('You do not have permission to access maintenance schedule support data.');
        }

        return ApiResponse::success([
            'types' => config('fleet.maintenance_schedule.types', []),
            'statuses' => config('fleet.maintenance_schedule.statuses', []),
            'vehicles' => Vehicle::query()
                ->where('status', '!=', 'decommissioned')
                ->orderBy('registration_number')
                ->get(['id', 'registration_number', 'make', 'model', 'odometer_reading'])
                ->map(fn (Vehicle $vehicle) => [
                    'id' => $vehicle->id,
                    'label' => $vehicle->registration_number,
                    'secondary' => trim($vehicle->make.' '.$vehicle->model),
                    'odometer_reading' => $vehicle->odometer_reading,
                ]),
            'service_providers' => ServiceProvider::query()
                ->where('status', 'active')
                ->whereIn('provider_type', ['garage', 'inspection_center', 'other'])
                ->orderBy('name')
                ->get(['id', 'name', 'provider_type'])
                ->map(fn (ServiceProvider $provider) => [
                    'id' => $provider->id,
                    'label' => $provider->name,
                    'secondary' => $provider->provider_type,
                ]),
        ]);
    }

    public function upcoming(): JsonResponse
    {
        $this->authorize('viewAny', MaintenanceSchedule::class);

        return ApiResponse::success(
            MaintenanceScheduleResource::collection($this->maintenanceService->upcomingSchedules())
        );
    }

    public function overdue(): JsonResponse
    {
        $this->authorize('viewAny', MaintenanceSchedule::class);

        return ApiResponse::success(
            MaintenanceScheduleResource::collection($this->maintenanceService->overdueSchedules())
        );
    }

    public function store(MaintenanceScheduleRequest $request): JsonResponse
    {
        $this->authorize('create', MaintenanceSchedule::class);

        $schedule = $this->maintenanceService->createSchedule($request->validated());

        return ApiResponse::created(new MaintenanceScheduleResource($schedule), 'Maintenance schedule created successfully.');
    }

    public function show(MaintenanceSchedule $maintenanceSchedule): JsonResponse
    {
        $this->authorize('view', $maintenanceSchedule);

        return ApiResponse::success(
            new MaintenanceScheduleResource($maintenanceSchedule->load(['vehicle', 'serviceProvider']))
        );
    }

    public function update(MaintenanceScheduleRequest $request, MaintenanceSchedule $maintenanceSchedule): JsonResponse
    {
        $this->authorize('update', $maintenanceSchedule);

        $schedule = $this->maintenanceService->updateSchedule($maintenanceSchedule, $request->validated());

        return ApiResponse::success(new MaintenanceScheduleResource($schedule), 'Maintenance schedule updated successfully.');
    }

    public function destroy(MaintenanceSchedule $maintenanceSchedule): JsonResponse
    {
        $this->authorize('delete', $maintenanceSchedule);

        if ($maintenanceSchedule->workOrders()->whereIn('status', ['open', 'in_progress'])->exists()) {
            return ApiResponse::error('Schedules with active work orders cannot be deleted.', 422);
        }

        $maintenanceSchedule->delete();

        return ApiResponse::noContent('Maintenance schedule deleted successfully.');
    }
}
