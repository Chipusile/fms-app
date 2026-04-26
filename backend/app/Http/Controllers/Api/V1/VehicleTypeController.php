<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\VehicleTypeRequest;
use App\Http\Resources\Api\V1\VehicleTypeResource;
use App\Models\VehicleType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VehicleType::class);

        $allowedSorts = ['name', 'code', 'created_at'];
        $sort = in_array($request->input('sort'), $allowedSorts, true) ? $request->input('sort') : 'name';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $vehicleTypes = VehicleType::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%");
                });
            })
            ->when($request->has('filter.is_active'), fn ($query) => $query->where('is_active', filter_var($request->input('filter.is_active'), FILTER_VALIDATE_BOOL)))
            ->orderBy($sort, $direction)
            ->paginate($this->perPage($request, 15));

        return ApiResponse::success(
            VehicleTypeResource::collection($vehicleTypes),
            meta: [
                'current_page' => $vehicleTypes->currentPage(),
                'last_page' => $vehicleTypes->lastPage(),
                'per_page' => $vehicleTypes->perPage(),
                'total' => $vehicleTypes->total(),
            ]
        );
    }

    public function store(VehicleTypeRequest $request): JsonResponse
    {
        $this->authorize('create', VehicleType::class);

        $vehicleType = VehicleType::create($request->validated());

        return ApiResponse::created(new VehicleTypeResource($vehicleType));
    }

    public function show(VehicleType $vehicleType): JsonResponse
    {
        $this->authorize('view', $vehicleType);

        return ApiResponse::success(new VehicleTypeResource($vehicleType));
    }

    public function update(VehicleTypeRequest $request, VehicleType $vehicleType): JsonResponse
    {
        $this->authorize('update', $vehicleType);

        $vehicleType->update($request->validated());

        return ApiResponse::success(new VehicleTypeResource($vehicleType->fresh()), 'Vehicle type updated successfully.');
    }

    public function destroy(VehicleType $vehicleType): JsonResponse
    {
        $this->authorize('delete', $vehicleType);

        if ($vehicleType->vehicles()->exists()) {
            return ApiResponse::error('Vehicle type cannot be deleted while vehicles reference it.', 422);
        }

        $vehicleType->delete();

        return ApiResponse::noContent('Vehicle type deleted successfully.');
    }
}
