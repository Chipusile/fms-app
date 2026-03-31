<?php

namespace App\Services\Operations;

use App\Models\Incident;
use App\Models\Setting;
use App\Models\User;
use App\Services\Workflow\ApprovalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IncidentService
{
    public function __construct(
        private readonly ApprovalService $approvalService,
    ) {
    }

    public function create(array $payload, User $actor): Incident
    {
        return DB::transaction(function () use ($payload, $actor) {
            $requiresReview = $this->requiresReview((string) $payload['severity']);

            $incident = Incident::create([
                ...$payload,
                'reported_by' => $actor->id,
                'incident_number' => $this->generateIncidentNumber($actor->tenant_id),
                'reported_at' => isset($payload['reported_at']) ? Carbon::parse($payload['reported_at']) : now(),
                'occurred_at' => Carbon::parse($payload['occurred_at']),
                'status' => $requiresReview ? 'under_review' : 'reported',
                'metadata' => [
                    'review_required' => $requiresReview,
                ],
            ]);

            if ($requiresReview) {
                $this->approvalService->createPending(
                    approvalable: $incident,
                    approvalType: 'incident_review',
                    requester: $actor,
                    title: "Incident {$incident->incident_number} requires review",
                    summary: 'A high-severity incident was reported and needs operational approval review.',
                    metadata: ['severity' => $incident->severity],
                );
            }

            return $incident->load(['vehicle', 'driver', 'trip', 'reporter', 'assignee']);
        });
    }

    public function update(Incident $incident, array $payload): Incident
    {
        if (in_array($incident->status, ['closed', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Closed or rejected incidents cannot be modified.'],
            ]);
        }

        $incident->update([
            ...$payload,
            'reported_at' => isset($payload['reported_at']) ? Carbon::parse($payload['reported_at']) : $incident->reported_at,
            'occurred_at' => isset($payload['occurred_at']) ? Carbon::parse($payload['occurred_at']) : $incident->occurred_at,
        ]);

        return $incident->fresh(['vehicle', 'driver', 'trip', 'reporter', 'assignee', 'approvalRequests']);
    }

    public function resolve(Incident $incident, string $resolutionNotes): Incident
    {
        if (in_array($incident->status, ['closed', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Closed or rejected incidents cannot be resolved.'],
            ]);
        }

        if ($incident->approvalRequests()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages([
                'status' => ['Pending approval requests must be decided before resolving the incident.'],
            ]);
        }

        $incident->update([
            'status' => 'resolved',
            'resolution_notes' => $resolutionNotes,
        ]);

        return $incident->fresh(['vehicle', 'driver', 'trip', 'reporter', 'assignee', 'approvalRequests']);
    }

    public function close(Incident $incident): Incident
    {
        if ($incident->status !== 'resolved') {
            throw ValidationException::withMessages([
                'status' => ['Only resolved incidents can be closed.'],
            ]);
        }

        $incident->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return $incident->fresh(['vehicle', 'driver', 'trip', 'reporter', 'assignee', 'approvalRequests']);
    }

    private function requiresReview(string $severity): bool
    {
        $severities = Setting::getValue('approvals.incident_review_severities', ['high', 'critical']);

        if (! is_array($severities)) {
            $severities = ['high', 'critical'];
        }

        return in_array($severity, $severities, true);
    }

    private function generateIncidentNumber(int $tenantId): string
    {
        $year = now()->format('Y');
        $prefix = config('fleet.incident.number_prefix', 'INC').'-'.$year.'-';

        $lastNumber = Incident::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('incident_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('incident_number');

        $sequence = $lastNumber
            ? ((int) str($lastNumber)->afterLast('-')->toString()) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
