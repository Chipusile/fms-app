<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\ApprovalDecisionRequest;
use App\Http\Resources\Api\V1\ApprovalRequestResource;
use App\Models\ApprovalRequest;
use App\Services\Workflow\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalRequestController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvalService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApprovalRequest::class);

        $approvals = ApprovalRequest::query()
            ->with(['requester', 'decider', 'approvalable'])
            ->when($request->filled('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->filled('filter.approval_type'), fn ($query, $type) => $query->where('approval_type', $type))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request, 15));

        return ApiResponse::success(
            ApprovalRequestResource::collection($approvals),
            meta: [
                'current_page' => $approvals->currentPage(),
                'last_page' => $approvals->lastPage(),
                'per_page' => $approvals->perPage(),
                'total' => $approvals->total(),
            ]
        );
    }

    public function show(ApprovalRequest $approvalRequest): JsonResponse
    {
        $this->authorize('view', $approvalRequest);

        return ApiResponse::success(
            new ApprovalRequestResource($approvalRequest->load(['requester', 'decider', 'approvalable']))
        );
    }

    public function approve(ApprovalDecisionRequest $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $this->authorize('decide', $approvalRequest);

        $approvalRequest = $this->approvalService->approve(
            $approvalRequest,
            $request->user(),
            $request->string('decision_notes')->toString() ?: null,
        );

        return ApiResponse::success(new ApprovalRequestResource($approvalRequest), 'Approval request approved successfully.');
    }

    public function reject(ApprovalDecisionRequest $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $this->authorize('decide', $approvalRequest);

        $approvalRequest = $this->approvalService->reject(
            $approvalRequest,
            $request->user(),
            $request->string('decision_notes')->toString() ?: null,
        );

        return ApiResponse::success(new ApprovalRequestResource($approvalRequest), 'Approval request rejected successfully.');
    }
}
