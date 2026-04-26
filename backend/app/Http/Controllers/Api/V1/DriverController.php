<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\DriverRequest;
use App\Http\Resources\Api\V1\DriverResource;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Driver::class);

        $allowedSorts = ['name', 'license_expiry_date', 'created_at'];
        $sort = in_array($request->input('sort'), $allowedSorts, true) ? $request->input('sort') : 'name';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $drivers = Driver::query()
            ->with(['department', 'user'])
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('employee_number', 'ilike', "%{$search}%")
                        ->orWhere('license_number', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->when($request->input('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('filter.department_id'), fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->orderBy($sort, $direction)
            ->paginate($this->perPage($request, 15));

        return ApiResponse::success(
            DriverResource::collection($drivers),
            meta: [
                'current_page' => $drivers->currentPage(),
                'last_page' => $drivers->lastPage(),
                'per_page' => $drivers->perPage(),
                'total' => $drivers->total(),
            ]
        );
    }

    public function store(DriverRequest $request): JsonResponse
    {
        $this->authorize('create', Driver::class);
        $this->enforceTenantPlanLimit($request, 'drivers', Driver::query()->count());

        $driver = Driver::create($request->validated());

        return ApiResponse::created(new DriverResource($driver->load(['department', 'user'])));
    }

    public function show(Driver $driver): JsonResponse
    {
        $this->authorize('view', $driver);

        return ApiResponse::success(new DriverResource($driver->load(['department', 'user'])));
    }

    public function update(DriverRequest $request, Driver $driver): JsonResponse
    {
        $this->authorize('update', $driver);

        $driver->update($request->validated());

        return ApiResponse::success(new DriverResource($driver->fresh()->load(['department', 'user'])), 'Driver updated successfully.');
    }

    public function destroy(Driver $driver): JsonResponse
    {
        $this->authorize('delete', $driver);

        if ($driver->assignments()->where('status', 'active')->exists()) {
            return ApiResponse::error('Driver cannot be deleted while active vehicle assignments exist.', 422);
        }

        $driver->delete();

        return ApiResponse::noContent('Driver deleted successfully.');
    }
}
