<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\MaintenanceRequestConvertRequest;
use App\Http\Requests\Api\V1\MaintenanceRequestDecisionRequest;
use App\Http\Requests\Api\V1\MaintenanceRequestRequest;
use App\Http\Resources\Api\V1\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Maintenance\MaintenanceRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceRequestController extends Controller
{
    public function __construct(
        private readonly MaintenanceRequestService $maintenanceRequestService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MaintenanceRequest::class);

        $requests = MaintenanceRequest::query()
            ->with(['schedule', 'vehicle', 'serviceProvider', 'requester', 'reviewer', 'workOrder'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('request_number', 'ilike', $search)
                        ->orWhere('title', 'ilike', $search)
                        ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('registration_number', 'ilike', $search));
                });
            })
            ->when($request->filled('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->filled('filter.request_type'), fn ($query, $type) => $query->where('request_type', $type))
            ->when($request->filled('filter.vehicle_id'), fn ($query, $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->orderByRaw("CASE WHEN status = 'submitted' THEN 0 WHEN status = 'approved' THEN 1 ELSE 2 END")
            ->orderByDesc('requested_at')
            ->paginate($this->perPage($request, 15));

        return ApiResponse::success(
            MaintenanceRequestResource::collection($requests),
            meta: [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ]
        );
    }

    public function supportData(Request $request): JsonResponse
    {
        if (! $request->user()?->hasAnyPermission(['maintenance.view', 'maintenance.create', 'maintenance.update', 'maintenance.approve'])) {
            return ApiResponse::forbidden('You do not have permission to access maintenance request support data.');
        }

        return ApiResponse::success([
            'types' => config('fleet.maintenance_request.types', []),
            'priorities' => config('fleet.maintenance_request.priorities', []),
            'statuses' => config('fleet.maintenance_request.statuses', []),
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
            'schedules' => MaintenanceSchedule::query()
                ->where('status', 'active')
                ->orderBy('title')
                ->get(['id', 'title', 'vehicle_id', 'schedule_type'])
                ->map(fn (MaintenanceSchedule $schedule) => [
                    'id' => $schedule->id,
                    'label' => $schedule->title,
                    'secondary' => $schedule->schedule_type,
                    'vehicle_id' => $schedule->vehicle_id,
                ]),
            'service_providers' => ServiceProvider::query()
                ->where('status', 'active')
                ->whereIn('provider_type', ['garage', 'inspection_center', 'tyre_shop', 'other'])
                ->orderBy('name')
                ->get(['id', 'name', 'provider_type'])
                ->map(fn (ServiceProvider $provider) => [
                    'id' => $provider->id,
                    'label' => $provider->name,
                    'secondary' => $provider->provider_type,
                ]),
            'assignees' => User::query()
                ->visibleTo($request->user())
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'label' => $user->name,
                    'secondary' => $user->email,
                ]),
        ]);
    }

    public function store(MaintenanceRequestRequest $request): JsonResponse
    {
        $this->authorize('create', MaintenanceRequest::class);

        $record = $this->maintenanceRequestService->create($request->validated(), $request->user());

        return ApiResponse::created(new MaintenanceRequestResource($record), 'Maintenance request created successfully.');
    }

    public function show(MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $this->authorize('view', $maintenanceRequest);

        return ApiResponse::success(
            new MaintenanceRequestResource($maintenanceRequest->load(['schedule', 'vehicle', 'serviceProvider', 'requester', 'reviewer', 'workOrder']))
        );
    }

    public function update(MaintenanceRequestRequest $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $this->authorize('update', $maintenanceRequest);

        $record = $this->maintenanceRequestService->update($maintenanceRequest, $request->validated());

        return ApiResponse::success(new MaintenanceRequestResource($record), 'Maintenance request updated successfully.');
    }

    public function approve(MaintenanceRequestDecisionRequest $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $this->authorize('approve', $maintenanceRequest);

        $record = $this->maintenanceRequestService->approve(
            $maintenanceRequest,
            $request->string('review_notes')->toString() ?: null,
            $request->user(),
        );

        return ApiResponse::success(new MaintenanceRequestResource($record), 'Maintenance request approved successfully.');
    }

    public function reject(MaintenanceRequestDecisionRequest $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $this->authorize('approve', $maintenanceRequest);

        $record = $this->maintenanceRequestService->reject(
            $maintenanceRequest,
            $request->string('review_notes')->toString() ?: null,
            $request->user(),
        );

        return ApiResponse::success(new MaintenanceRequestResource($record), 'Maintenance request rejected successfully.');
    }

    public function cancel(MaintenanceRequestDecisionRequest $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $this->authorize('update', $maintenanceRequest);

        $record = $this->maintenanceRequestService->cancel(
            $maintenanceRequest,
            $request->string('review_notes')->toString() ?: null,
        );

        return ApiResponse::success(new MaintenanceRequestResource($record), 'Maintenance request cancelled successfully.');
    }

    public function convert(MaintenanceRequestConvertRequest $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $this->authorize('convert', $maintenanceRequest);

        $record = $this->maintenanceRequestService->convertToWorkOrder($maintenanceRequest, $request->validated(), $request->user());

        return ApiResponse::success(new MaintenanceRequestResource($record), 'Maintenance request converted to work order successfully.');
    }

    public function destroy(MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $this->authorize('delete', $maintenanceRequest);

        if ($maintenanceRequest->workOrder()->exists()) {
            return ApiResponse::error('Converted maintenance requests cannot be deleted.', 422);
        }

        $maintenanceRequest->delete();

        return ApiResponse::noContent('Maintenance request deleted successfully.');
    }
}
