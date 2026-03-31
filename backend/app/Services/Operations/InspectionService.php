<?php

namespace App\Services\Operations;

use App\Models\Inspection;
use App\Models\InspectionResponse;
use App\Models\InspectionTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Workflow\ApprovalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InspectionService
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly OdometerService $odometerService,
    ) {
    }

    public function create(array $payload, User $actor): Inspection
    {
        return DB::transaction(function () use ($payload, $actor) {
            $template = InspectionTemplate::query()
                ->with('items')
                ->findOrFail($payload['inspection_template_id']);

            if ($template->status !== 'active') {
                throw ValidationException::withMessages([
                    'inspection_template_id' => ['Only active inspection templates can be used.'],
                ]);
            }

            $vehicle = Vehicle::query()->findOrFail($payload['vehicle_id']);
            $responses = $payload['responses'] ?? [];
            $templateItems = $template->items->keyBy('id');
            $requiredItemIds = $template->items->where('is_required', true)->pluck('id')->all();
            $responseItemIds = collect($responses)->pluck('template_item_id')->filter()->map(fn ($value) => (int) $value)->all();

            $missingRequired = array_diff($requiredItemIds, $responseItemIds);

            if ($missingRequired !== []) {
                throw ValidationException::withMessages([
                    'responses' => ['All required checklist items must be answered.'],
                ]);
            }

            $failedItems = 0;
            $criticalDefects = 0;
            $normalisedResponses = [];

            foreach ($responses as $index => $response) {
                $item = $templateItems->get((int) $response['template_item_id']);

                if (! $item) {
                    throw ValidationException::withMessages([
                        'responses' => ['Each response must belong to the selected inspection template.'],
                    ]);
                }

                $isPass = $response['is_pass'] ?? null;
                if ($item->response_type === 'pass_fail' && $isPass === null) {
                    throw ValidationException::withMessages([
                        "responses.{$index}.is_pass" => ['Pass or fail is required for pass/fail checklist items.'],
                    ]);
                }

                if ($isPass === false) {
                    $failedItems++;
                }

                $defectSeverity = $response['defect_severity'] ?? null;
                if ($defectSeverity === 'critical') {
                    $criticalDefects++;
                }

                $normalisedResponses[] = [
                    'tenant_id' => $actor->tenant_id,
                    'inspection_template_item_id' => $item->id,
                    'item_label' => $item->title,
                    'response_value' => ['value' => $response['response_value'] ?? null],
                    'is_pass' => $isPass,
                    'defect_severity' => $defectSeverity,
                    'defect_summary' => $response['defect_summary'] ?? null,
                    'notes' => $response['notes'] ?? null,
                    'sort_order' => $item->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $inspection = Inspection::create([
                'tenant_id' => $actor->tenant_id,
                'inspection_template_id' => $template->id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $payload['driver_id'] ?? null,
                'trip_id' => $payload['trip_id'] ?? null,
                'inspected_by' => $actor->id,
                'inspection_number' => $this->generateInspectionNumber($actor->tenant_id),
                'performed_at' => Carbon::parse($payload['performed_at']),
                'odometer_reading' => $payload['odometer_reading'] ?? null,
                'result' => $failedItems > 0 ? 'fail' : 'pass',
                'status' => $failedItems > 0 ? 'requires_action' : 'completed',
                'total_items' => $template->items->count(),
                'failed_items' => $failedItems,
                'critical_defects' => $criticalDefects,
                'notes' => $payload['notes'] ?? null,
                'metadata' => [
                    'review_required' => $criticalDefects > 0 && $this->requiresCriticalReview($template),
                ],
            ]);

            $inspection->responses()->createMany($normalisedResponses);

            if (! empty($payload['odometer_reading'])) {
                $this->odometerService->record(
                    vehicle: $vehicle,
                    reading: (int) $payload['odometer_reading'],
                    source: 'inspection',
                    sourceReferenceId: $inspection->id,
                    recordedAt: $inspection->performed_at,
                    driverId: $inspection->driver_id,
                    notes: 'Captured from inspection.',
                );
            }

            if ($criticalDefects > 0 && $this->requiresCriticalReview($template)) {
                $this->approvalService->createPending(
                    approvalable: $inspection,
                    approvalType: 'inspection_review',
                    requester: $actor,
                    title: "Inspection {$inspection->inspection_number} requires review",
                    summary: 'Critical defects were recorded during inspection and require operational review.',
                    metadata: ['critical_defects' => $criticalDefects],
                );
            }

            return $inspection->load(['template.items', 'vehicle', 'driver', 'trip', 'inspector', 'responses']);
        });
    }

    public function close(Inspection $inspection, ?string $resolutionNotes = null): Inspection
    {
        if ($inspection->status === 'closed') {
            throw ValidationException::withMessages([
                'status' => ['This inspection is already closed.'],
            ]);
        }

        if ($inspection->approvalRequests()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages([
                'status' => ['Pending approval requests must be decided before the inspection can be closed.'],
            ]);
        }

        $inspection->update([
            'status' => 'closed',
            'closed_at' => now(),
            'resolution_notes' => $resolutionNotes,
        ]);

        return $inspection->fresh(['template.items', 'vehicle', 'driver', 'trip', 'inspector', 'responses', 'approvalRequests']);
    }

    private function requiresCriticalReview(InspectionTemplate $template): bool
    {
        return $template->requires_review_on_critical
            && (bool) Setting::getValue('approvals.inspection_critical_requires_review', true);
    }

    private function generateInspectionNumber(int $tenantId): string
    {
        $year = now()->format('Y');
        $prefix = config('fleet.inspection.number_prefix', 'INSP').'-'.$year.'-';

        $lastNumber = Inspection::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('inspection_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('inspection_number');

        $sequence = $lastNumber
            ? ((int) str($lastNumber)->afterLast('-')->toString()) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
