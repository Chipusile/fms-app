<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Resources\Api\V1\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * List all permissions, optionally grouped by module.
     * Permissions are read-only — they are seeded, not user-created.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Permission::class);

        $permissions = Permission::query()
            ->when($request->input('filter.module'), fn ($q, $module) => $q->where('module', $module))
            ->orderBy('module')
            ->orderBy('name')
            ->get();

        if ($request->boolean('grouped')) {
            $grouped = $permissions->groupBy('module')->map(fn ($group) => PermissionResource::collection($group));

            return ApiResponse::success($grouped);
        }

        return ApiResponse::success(PermissionResource::collection($permissions));
    }
}
