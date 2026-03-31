<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\VehicleComponentRequest;
use App\Http\Requests\Api\V1\VehicleComponentRetireRequest;
use App\Http\Resources\Api\V1\VehicleComponentResource;
use App\Models\ServiceProvider;
use App\Models\Vehicle;
use App\Models\VehicleComponent;
use App\Services\Maintenance\VehicleComponentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleComponentController extends Controller
{
    public function __construct(
        private readonly VehicleComponentService $vehicleComponentService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VehicleComponent::class);

        $this->vehicleComponentService->refreshStatuses($request->user()?->tenant_id);

        $components = VehicleComponent::query()
            ->with(['vehicle', 'serviceProvider'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('component_number', 'like', $search)
                        ->orWhere('serial_number', 'like', $search)
                        ->orWhere('brand', 'like', $search)
                        ->orWhere('model', 'like', $search)
                        ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('registration_number', 'like', $search));
                });
            })
            ->when($request->filled('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->filled('filter.component_type'), fn ($query, $type) => $query->where('component_type', $type))
            ->when($request->filled('filter.vehicle_id'), fn ($query, $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->orderByRaw("CASE WHEN status = 'due_replacement' THEN 0 WHEN status = 'active' THEN 1 ELSE 2 END")
            ->orderBy('next_replacement_at')
            ->orderBy('next_replacement_km')
            ->paginate($request->input('per_page', 15));

        return ApiResponse::success(
            VehicleComponentResource::collection($components),
            meta: [
                'current_page' => $components->currentPage(),
                'last_page' => $components->lastPage(),
                'per_page' => $components->perPage(),
                'total' => $components->total(),
            ]
        );
    }

    public function supportData(Request $request): JsonResponse
    {
        if (! $request->user()?->hasAnyPermission(['maintenance.view', 'maintenance.create', 'maintenance.update'])) {
            return ApiResponse::forbidden('You do not have permission to access component support data.');
        }

        return ApiResponse::success([
            'types' => config('fleet.vehicle_component.types', []),
            'statuses' => config('fleet.vehicle_component.statuses', []),
            'condition_statuses' => config('fleet.vehicle_component.condition_statuses', []),
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
                ->whereIn('provider_type', ['garage', 'tyre_shop', 'other'])
                ->orderBy('name')
                ->get(['id', 'name', 'provider_type'])
                ->map(fn (ServiceProvider $provider) => [
                    'id' => $provider->id,
                    'label' => $provider->name,
                    'secondary' => $provider->provider_type,
                ]),
        ]);
    }

    public function dueSoon(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VehicleComponent::class);

        return ApiResponse::success(
            VehicleComponentResource::collection(
                $this->vehicleComponentService->dueSoonComponents($request->user()?->tenant_id)
            )
        );
    }

    public function overdue(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VehicleComponent::class);

        return ApiResponse::success(
            VehicleComponentResource::collection(
                $this->vehicleComponentService->overdueComponents($request->user()?->tenant_id)
            )
        );
    }

    public function store(VehicleComponentRequest $request): JsonResponse
    {
        $this->authorize('create', VehicleComponent::class);

        $component = $this->vehicleComponentService->create($request->validated());

        return ApiResponse::created(new VehicleComponentResource($component), 'Vehicle component created successfully.');
    }

    public function show(VehicleComponent $vehicleComponent): JsonResponse
    {
        $this->authorize('view', $vehicleComponent);

        return ApiResponse::success(
            new VehicleComponentResource($vehicleComponent->load(['vehicle', 'serviceProvider']))
        );
    }

    public function update(VehicleComponentRequest $request, VehicleComponent $vehicleComponent): JsonResponse
    {
        $this->authorize('update', $vehicleComponent);

        $component = $this->vehicleComponentService->update($vehicleComponent, $request->validated());

        return ApiResponse::success(new VehicleComponentResource($component), 'Vehicle component updated successfully.');
    }

    public function retire(VehicleComponentRetireRequest $request, VehicleComponent $vehicleComponent): JsonResponse
    {
        $this->authorize('update', $vehicleComponent);

        $component = $this->vehicleComponentService->retire($vehicleComponent, $request->validated());

        return ApiResponse::success(new VehicleComponentResource($component), 'Vehicle component retired successfully.');
    }

    public function destroy(VehicleComponent $vehicleComponent): JsonResponse
    {
        $this->authorize('delete', $vehicleComponent);

        $vehicleComponent->delete();

        return ApiResponse::noContent('Vehicle component deleted successfully.');
    }
}
