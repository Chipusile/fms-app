<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\DepartmentRequest;
use App\Http\Resources\Api\V1\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Department::class);

        $allowedSorts = ['name', 'code', 'created_at'];
        $sort = in_array($request->input('sort'), $allowedSorts, true) ? $request->input('sort') : 'name';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $departments = Department::query()
            ->with('manager')
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%");
                });
            })
            ->when($request->input('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy($sort, $direction)
            ->paginate($this->perPage($request, 15));

        return ApiResponse::success(
            DepartmentResource::collection($departments),
            meta: [
                'current_page' => $departments->currentPage(),
                'last_page' => $departments->lastPage(),
                'per_page' => $departments->perPage(),
                'total' => $departments->total(),
            ]
        );
    }

    public function store(DepartmentRequest $request): JsonResponse
    {
        $this->authorize('create', Department::class);

        $department = Department::create($request->validated());

        return ApiResponse::created(new DepartmentResource($department->load('manager')));
    }

    public function show(Department $department): JsonResponse
    {
        $this->authorize('view', $department);

        return ApiResponse::success(new DepartmentResource($department->load('manager')));
    }

    public function update(DepartmentRequest $request, Department $department): JsonResponse
    {
        $this->authorize('update', $department);

        $department->update($request->validated());

        return ApiResponse::success(new DepartmentResource($department->fresh()->load('manager')), 'Department updated successfully.');
    }

    public function destroy(Department $department): JsonResponse
    {
        $this->authorize('delete', $department);

        if ($department->vehicles()->exists() || $department->drivers()->exists()) {
            return ApiResponse::error('Department cannot be deleted while vehicles or drivers reference it.', 422);
        }

        $department->delete();

        return ApiResponse::noContent('Department deleted successfully.');
    }
}
