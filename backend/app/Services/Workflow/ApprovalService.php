<?php

namespace App\Services\Workflow;

use App\Models\ApprovalRequest;
use App\Models\Incident;
use App\Models\Inspection;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public function createPending(
        Model $approvalable,
        string $approvalType,
        User $requester,
        string $title,
        ?string $summary = null,
        CarbonInterface|string|null $dueAt = null,
        array $metadata = [],
    ): ApprovalRequest {
        $existing = ApprovalRequest::withoutGlobalScopes()
            ->where('tenant_id', $requester->tenant_id)
            ->where('approvalable_type', $approvalable->getMorphClass())
            ->where('approvalable_id', $approvalable->getKey())
            ->where('approval_type', $approvalType)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return $existing->fresh(['requester', 'decider', 'approvalable']);
        }

        $approval = ApprovalRequest::create([
            'tenant_id' => $requester->tenant_id,
            'approvalable_type' => $approvalable->getMorphClass(),
            'approvalable_id' => $approvalable->getKey(),
            'approval_type' => $approvalType,
            'requested_by' => $requester->id,
            'title' => $title,
            'summary' => $summary,
            'status' => 'pending',
            'due_at' => $dueAt,
            'metadata' => $metadata,
        ]);

        $approvers = $this->approverUsers($requester->tenant_id, $requester->id);

        if ($approvers->isNotEmpty()) {
            $this->notificationService->notifyUsers(
                $approvers,
                'approval_pending',
                $title,
                $summary ?? 'A new approval request is waiting in your queue.',
                '/approvals',
                $approval,
                ['approval_type' => $approvalType]
            );
        }

        return $approval->fresh(['requester', 'decider', 'approvalable']);
    }

    public function approve(ApprovalRequest $approvalRequest, User $actor, ?string $decisionNotes = null): ApprovalRequest
    {
        return $this->decide($approvalRequest, $actor, 'approved', $decisionNotes);
    }

    public function reject(ApprovalRequest $approvalRequest, User $actor, ?string $decisionNotes = null): ApprovalRequest
    {
        return $this->decide($approvalRequest, $actor, 'rejected', $decisionNotes);
    }

    private function decide(
        ApprovalRequest $approvalRequest,
        User $actor,
        string $decision,
        ?string $decisionNotes = null
    ): ApprovalRequest {
        if ($approvalRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending approval requests can be decided.'],
            ]);
        }

        DB::transaction(function () use ($approvalRequest, $actor, $decision, $decisionNotes) {
            $approvalRequest->update([
                'status' => $decision,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_notes' => $decisionNotes,
            ]);

            $this->applyDecisionToApprovalable($approvalRequest, $decision, $decisionNotes);

            $requester = $approvalRequest->requester()->first();

            if ($requester) {
                $subjectTitle = $approvalRequest->title;
                $message = $decision === 'approved'
                    ? 'Your approval request was approved.'
                    : 'Your approval request was rejected.';

                $this->notificationService->notifyUser(
                    $requester,
                    'approval_decided',
                    $subjectTitle,
                    $message,
                    '/approvals',
                    $approvalRequest,
                    ['decision' => $decision]
                );
            }
        });

        return $approvalRequest->fresh(['requester', 'decider', 'approvalable']);
    }

    private function applyDecisionToApprovalable(
        ApprovalRequest $approvalRequest,
        string $decision,
        ?string $decisionNotes = null
    ): void {
        $approvalable = $approvalRequest->approvalable;

        if ($approvalable instanceof Inspection) {
            $metadata = $approvalable->metadata ?? [];
            $metadata['last_review_outcome'] = $decision;
            $metadata['last_review_notes'] = $decisionNotes;

            $approvalable->update([
                'status' => $decision === 'approved' ? 'reviewed' : 'requires_action',
                'reviewed_at' => now(),
                'metadata' => $metadata,
            ]);

            return;
        }

        if ($approvalable instanceof Incident) {
            $metadata = $approvalable->metadata ?? [];
            $metadata['last_review_outcome'] = $decision;
            $metadata['last_review_notes'] = $decisionNotes;

            $approvalable->update([
                'status' => $decision === 'approved' ? 'action_required' : 'rejected',
                'metadata' => $metadata,
            ]);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function approverUsers(int $tenantId, ?int $excludeUserId = null): Collection
    {
        return $this->notificationService->recipientsWithPermission(
            tenantId: $tenantId,
            permissionSlug: 'approvals.decide',
            excludeUserId: $excludeUserId,
        );
    }
}
