<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\VehicleRequest;
use App\Http\Resources\Api\V1\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vehicle::class);

        $allowedSorts = ['registration_number', 'make', 'model', 'year', 'created_at'];
        $sort = in_array($request->input('sort'), $allowedSorts, true) ? $request->input('sort') : 'registration_number';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $vehicles = Vehicle::query()
            ->with(['type', 'department'])
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('registration_number', 'ilike', "%{$search}%")
                        ->orWhere('asset_tag', 'ilike', "%{$search}%")
                        ->orWhere('vin', 'ilike', "%{$search}%")
                        ->orWhere('make', 'ilike', "%{$search}%")
                        ->orWhere('model', 'ilike', "%{$search}%");
                });
            })
            ->when($request->input('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('filter.fuel_type'), fn ($query, $fuelType) => $query->where('fuel_type', $fuelType))
            ->when($request->input('filter.vehicle_type_id'), fn ($query, $vehicleTypeId) => $query->where('vehicle_type_id', $vehicleTypeId))
            ->when($request->input('filter.department_id'), fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->orderBy($sort, $direction)
            ->paginate($this->perPage($request, 15));

        return ApiResponse::success(
            VehicleResource::collection($vehicles),
            meta: [
                'current_page' => $vehicles->currentPage(),
                'last_page' => $vehicles->lastPage(),
                'per_page' => $vehicles->perPage(),
                'total' => $vehicles->total(),
            ]
        );
    }

    public function store(VehicleRequest $request): JsonResponse
    {
        $this->authorize('create', Vehicle::class);
        $this->enforceTenantPlanLimit($request, 'vehicles', Vehicle::query()->count());

        $vehicle = Vehicle::create($request->validated());

        return ApiResponse::created(new VehicleResource($vehicle->load(['type', 'department'])));
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        return ApiResponse::success(new VehicleResource($vehicle->load(['type', 'department'])));
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $vehicle->update($request->validated());

        return ApiResponse::success(new VehicleResource($vehicle->fresh()->load(['type', 'department'])), 'Vehicle updated successfully.');
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('delete', $vehicle);

        if ($vehicle->assignments()->where('status', 'active')->exists()) {
            return ApiResponse::error('Vehicle cannot be deleted while active assignments exist.', 422);
        }

        $vehicle->delete();

        return ApiResponse::noContent('Vehicle deleted successfully.');
    }
}
