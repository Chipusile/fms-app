<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\VehicleAssignmentRequest;
use App\Http\Resources\Api\V1\VehicleAssignmentResource;
use App\Models\Department;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VehicleAssignment::class);

        $allowedSorts = ['assigned_from', 'created_at'];
        $sort = in_array($request->input('sort'), $allowedSorts, true) ? $request->input('sort') : 'assigned_from';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $assignments = VehicleAssignment::query()
            ->with(['vehicle', 'driver', 'department'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->whereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('registration_number', 'like', $search))
                        ->orWhereHas('driver', fn ($driverQuery) => $driverQuery->where('name', 'like', $search))
                        ->orWhereHas('department', fn ($departmentQuery) => $departmentQuery->where('name', 'like', $search));
                });
            })
            ->when($request->input('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('filter.vehicle_id'), fn ($query, $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($request->input('filter.driver_id'), fn ($query, $driverId) => $query->where('driver_id', $driverId))
            ->when($request->input('filter.department_id'), fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->orderBy($sort, $direction)
            ->paginate($request->input('per_page', 15));

        return ApiResponse::success(
            VehicleAssignmentResource::collection($assignments),
            meta: [
                'current_page' => $assignments->currentPage(),
                'last_page' => $assignments->lastPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
            ]
        );
    }

    public function supportData(Request $request): JsonResponse
    {
        if (! $request->user()?->hasAnyPermission(['vehicles.view', 'vehicles.assign'])) {
            return ApiResponse::forbidden('You do not have permission to access assignment support data.');
        }

        return ApiResponse::success([
            'vehicles' => Vehicle::query()
                ->orderBy('registration_number')
                ->get(['id', 'registration_number', 'make', 'model'])
                ->map(fn (Vehicle $vehicle) => [
                    'id' => $vehicle->id,
                    'label' => $vehicle->registration_number,
                    'secondary' => trim($vehicle->make.' '.$vehicle->model),
                ]),
            'drivers' => Driver::query()
                ->orderBy('name')
                ->get(['id', 'name', 'license_number'])
                ->map(fn (Driver $driver) => [
                    'id' => $driver->id,
                    'label' => $driver->name,
                    'secondary' => $driver->license_number,
                ]),
            'departments' => Department::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Department $department) => [
                    'id' => $department->id,
                    'label' => $department->name,
                    'secondary' => $department->code,
                ]),
        ]);
    }

    public function store(VehicleAssignmentRequest $request): JsonResponse
    {
        $this->authorize('create', VehicleAssignment::class);

        $assignment = DB::transaction(function () use ($request) {
            $assignment = VehicleAssignment::create($request->validated());

            $this->refreshVehicleDepartment($assignment->vehicle_id);

            return $assignment;
        });

        return ApiResponse::created(new VehicleAssignmentResource($assignment->load(['vehicle', 'driver', 'department'])));
    }

    public function show(VehicleAssignment $vehicleAssignment): JsonResponse
    {
        $this->authorize('view', $vehicleAssignment);

        return ApiResponse::success(new VehicleAssignmentResource($vehicleAssignment->load(['vehicle', 'driver', 'department'])));
    }

    public function update(VehicleAssignmentRequest $request, VehicleAssignment $vehicleAssignment): JsonResponse
    {
        $this->authorize('update', $vehicleAssignment);

        DB::transaction(function () use ($request, $vehicleAssignment) {
            $previousVehicleId = $vehicleAssignment->vehicle_id;
            $vehicleAssignment->update($request->validated());

            $this->refreshVehicleDepartment($previousVehicleId);

            if ($previousVehicleId !== $vehicleAssignment->vehicle_id) {
                $this->refreshVehicleDepartment($vehicleAssignment->vehicle_id);
            }
        });

        return ApiResponse::success(new VehicleAssignmentResource($vehicleAssignment->fresh()->load(['vehicle', 'driver', 'department'])), 'Vehicle assignment updated successfully.');
    }

    public function destroy(VehicleAssignment $vehicleAssignment): JsonResponse
    {
        $this->authorize('delete', $vehicleAssignment);

        $vehicleId = $vehicleAssignment->vehicle_id;
        $vehicleAssignment->delete();
        $this->refreshVehicleDepartment($vehicleId);

        return ApiResponse::noContent('Vehicle assignment deleted successfully.');
    }

    private function refreshVehicleDepartment(int $vehicleId): void
    {
        $departmentId = VehicleAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where('status', 'active')
            ->whereNotNull('department_id')
            ->orderByDesc('assigned_from')
            ->orderByDesc('id')
            ->value('department_id');

        Vehicle::query()->whereKey($vehicleId)->update([
            'department_id' => $departmentId,
        ]);
    }
}
