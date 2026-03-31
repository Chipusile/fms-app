<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Resources\Api\V1\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * List audit logs with filtering support.
     * Audit logs are read-only — no create/update/delete endpoints.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->input('filter.event'), fn ($q, $event) => $q->where('event', $event))
            ->when($request->input('filter.auditable_type'), fn ($q, $type) => $q->where('auditable_type', $type))
            ->when($request->input('filter.auditable_id'), fn ($q, $id) => $q->where('auditable_id', $id))
            ->when($request->input('filter.user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->input('filter.from'), fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($request->input('filter.to'), fn ($q, $to) => $q->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 25));

        return ApiResponse::success(
            AuditLogResource::collection($logs->items()),
            meta: [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ]
        );
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        $this->authorize('view', $auditLog);

        return ApiResponse::success(
            new AuditLogResource($auditLog->load('user:id,name,email'))
        );
    }
}
