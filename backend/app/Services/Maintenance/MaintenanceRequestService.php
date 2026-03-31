<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Workflow\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceRequestService
{
    public function __construct(
        private readonly MaintenanceService $maintenanceService,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function create(array $payload, User $actor): MaintenanceRequest
    {
        return DB::transaction(function () use ($payload, $actor) {
            $request = MaintenanceRequest::create([
                'tenant_id' => $actor->tenant_id,
                'maintenance_schedule_id' => $payload['maintenance_schedule_id'] ?? null,
                'vehicle_id' => $payload['vehicle_id'],
                'service_provider_id' => $payload['service_provider_id'] ?? null,
                'requested_by' => $actor->id,
                'request_number' => $this->generateRequestNumber($actor->tenant_id),
                'title' => $payload['title'],
                'request_type' => $payload['request_type'],
                'priority' => $payload['priority'],
                'status' => 'submitted',
                'needed_by' => isset($payload['needed_by']) ? Carbon::parse($payload['needed_by'])->toDateString() : null,
                'requested_at' => now(),
                'odometer_reading' => $payload['odometer_reading'] ?? null,
                'description' => $payload['description'],
                'review_notes' => $payload['review_notes'] ?? null,
                'metadata' => [
                    'source' => 'manual_request',
                ],
            ]);

            $this->notifyApprovers($request, $actor);

            return $request->load(['schedule', 'vehicle', 'serviceProvider', 'requester', 'reviewer', 'workOrder']);
        });
    }

    public function update(MaintenanceRequest $maintenanceRequest, array $payload): MaintenanceRequest
    {
        if ($maintenanceRequest->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => ['Only submitted maintenance requests can be modified.'],
            ]);
        }

        return DB::transaction(function () use ($maintenanceRequest, $payload) {
            $maintenanceRequest->update([
                'maintenance_schedule_id' => $payload['maintenance_schedule_id'] ?? $maintenanceRequest->maintenance_schedule_id,
                'vehicle_id' => $payload['vehicle_id'] ?? $maintenanceRequest->vehicle_id,
                'service_provider_id' => $payload['service_provider_id'] ?? $maintenanceRequest->service_provider_id,
                'title' => $payload['title'] ?? $maintenanceRequest->title,
                'request_type' => $payload['request_type'] ?? $maintenanceRequest->request_type,
                'priority' => $payload['priority'] ?? $maintenanceRequest->priority,
                'needed_by' => isset($payload['needed_by'])
                    ? Carbon::parse($payload['needed_by'])->toDateString()
                    : $maintenanceRequest->needed_by?->toDateString(),
                'odometer_reading' => $payload['odometer_reading'] ?? $maintenanceRequest->odometer_reading,
                'description' => $payload['description'] ?? $maintenanceRequest->description,
                'review_notes' => array_key_exists('review_notes', $payload) ? $payload['review_notes'] : $maintenanceRequest->review_notes,
            ]);

            return $maintenanceRequest->fresh(['schedule', 'vehicle', 'serviceProvider', 'requester', 'reviewer', 'workOrder']);
        });
    }

    public function approve(MaintenanceRequest $maintenanceRequest, ?string $reviewNotes, User $actor): MaintenanceRequest
    {
        if ($maintenanceRequest->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => ['Only submitted maintenance requests can be approved.'],
            ]);
        }

        return DB::transaction(function () use ($maintenanceRequest, $reviewNotes, $actor) {
            $maintenanceRequest->update([
                'status' => 'approved',
                'reviewed_by' => $actor->id,
                'review_notes' => $reviewNotes,
                'metadata' => array_merge($maintenanceRequest->metadata ?? [], [
                    'approved_at' => now()->toISOString(),
                ]),
            ]);

            $this->notifyRequesterDecision($maintenanceRequest, true, $reviewNotes);

            return $maintenanceRequest->fresh(['schedule', 'vehicle', 'serviceProvider', 'requester', 'reviewer', 'workOrder']);
        });
    }

    public function reject(MaintenanceRequest $maintenanceRequest, ?string $reviewNotes, User $actor): MaintenanceRequest
    {
        if ($maintenanceRequest->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => ['Only submitted maintenance requests can be rejected.'],
            ]);
        }

        return DB::transaction(function () use ($maintenanceRequest, $reviewNotes, $actor) {
            $maintenanceRequest->update([
                'status' => 'rejected',
                'reviewed_by' => $actor->id,
                'review_notes' => $reviewNotes,
                'metadata' => array_merge($maintenanceRequest->metadata ?? [], [
                    'rejected_at' => now()->toISOString(),
                ]),
            ]);

            $this->notifyRequesterDecision($maintenanceRequest, false, $reviewNotes);

            return $maintenanceRequest->fresh(['schedule', 'vehicle', 'serviceProvider', 'requester', 'reviewer', 'workOrder']);
        });
    }

    public function cancel(MaintenanceRequest $maintenanceRequest, ?string $reviewNotes): MaintenanceRequest
    {
        if (! in_array($maintenanceRequest->status, ['submitted', 'approved'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only submitted or approved maintenance requests can be cancelled.'],
            ]);
        }

        return DB::transaction(function () use ($maintenanceRequest, $reviewNotes) {
            $maintenanceRequest->update([
                'status' => 'cancelled',
                'review_notes' => $reviewNotes ?: $maintenanceRequest->review_notes,
                'metadata' => array_merge($maintenanceRequest->metadata ?? [], [
                    'cancelled_at' => now()->toISOString(),
                ]),
            ]);

            return $maintenanceRequest->fresh(['schedule', 'vehicle', 'serviceProvider', 'requester', 'reviewer', 'workOrder']);
        });
    }

    public function convertToWorkOrder(MaintenanceRequest $maintenanceRequest, array $payload, User $actor): MaintenanceRequest
    {
        if ($maintenanceRequest->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => ['Only approved maintenance requests can be converted into work orders.'],
            ]);
        }

        if ($maintenanceRequest->workOrder()->exists()) {
            throw ValidationException::withMessages([
                'status' => ['This maintenance request already has a linked work order.'],
            ]);
        }

        return DB::transaction(function () use ($maintenanceRequest, $payload, $actor) {
            $workOrder = $this->maintenanceService->createWorkOrder([
                'maintenance_schedule_id' => $maintenanceRequest->maintenance_schedule_id,
                'maintenance_request_id' => $maintenanceRequest->id,
                'vehicle_id' => $maintenanceRequest->vehicle_id,
                'service_provider_id' => $payload['service_provider_id'] ?? $maintenanceRequest->service_provider_id,
                'assigned_to' => $payload['assigned_to'] ?? null,
                'title' => $payload['title'] ?? $maintenanceRequest->title,
                'maintenance_type' => $this->mapRequestTypeToWorkOrderType($maintenanceRequest->request_type),
                'priority' => $maintenanceRequest->priority,
                'due_date' => $payload['due_date'] ?? $maintenanceRequest->needed_by?->toDateString(),
                'estimated_cost' => $payload['estimated_cost'] ?? null,
                'notes' => trim(implode("\n\n", array_filter([
                    $maintenanceRequest->description,
                    $payload['notes'] ?? null,
                ]))),
            ], $actor);

            $maintenanceRequest->update([
                'status' => 'converted',
                'reviewed_by' => $maintenanceRequest->reviewed_by ?? $actor->id,
                'review_notes' => $payload['review_notes'] ?? $maintenanceRequest->review_notes,
                'metadata' => array_merge($maintenanceRequest->metadata ?? [], [
                    'converted_at' => now()->toISOString(),
                    'work_order_id' => $workOrder->id,
                ]),
            ]);

            $this->notificationService->notifyUser(
                $maintenanceRequest->requester()->firstOrFail(),
                'maintenance_request_decided',
                'Maintenance request converted',
                "Request {$maintenanceRequest->request_number} was converted to work order {$workOrder->work_order_number}.",
                "/work-orders/{$workOrder->id}/edit",
                $maintenanceRequest,
                ['work_order_id' => $workOrder->id],
            );

            return $maintenanceRequest->fresh(['schedule', 'vehicle', 'serviceProvider', 'requester', 'reviewer', 'workOrder']);
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function approversFor(int $tenantId, ?int $excludeUserId = null): Collection
    {
        return $this->notificationService->recipientsWithPermission($tenantId, 'maintenance.approve', $excludeUserId);
    }

    private function notifyApprovers(MaintenanceRequest $maintenanceRequest, User $actor): void
    {
        $recipients = $this->approversFor($maintenanceRequest->tenant_id, $actor->id);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->notificationService->notifyUsers(
            $recipients,
            'maintenance_request_submitted',
            'Maintenance request awaiting review',
            "Request {$maintenanceRequest->request_number} for {$maintenanceRequest->title} was submitted by {$actor->name}.",
            "/maintenance-requests/{$maintenanceRequest->id}/edit",
            $maintenanceRequest,
            ['priority' => $maintenanceRequest->priority],
        );
    }

    private function notifyRequesterDecision(MaintenanceRequest $maintenanceRequest, bool $approved, ?string $reviewNotes): void
    {
        $requester = $maintenanceRequest->requester()->first();

        if (! $requester) {
            return;
        }

        $decision = $approved ? 'approved' : 'rejected';
        $body = "Request {$maintenanceRequest->request_number} was {$decision}.";

        if ($reviewNotes) {
            $body .= " Notes: {$reviewNotes}";
        }

        $this->notificationService->notifyUser(
            $requester,
            'maintenance_request_decided',
            "Maintenance request {$decision}",
            $body,
            "/maintenance-requests/{$maintenanceRequest->id}/edit",
            $maintenanceRequest,
            ['status' => $maintenanceRequest->status],
        );
    }

    private function mapRequestTypeToWorkOrderType(string $requestType): string
    {
        return match ($requestType) {
            'breakdown' => 'corrective',
            'component_replacement' => 'corrective',
            default => $requestType,
        };
    }

    private function generateRequestNumber(int $tenantId): string
    {
        $prefix = config('fleet.maintenance_request.number_prefix', 'MR');
        $count = MaintenanceRequest::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->withTrashed()
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }
}
