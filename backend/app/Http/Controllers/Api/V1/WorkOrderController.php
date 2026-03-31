<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\WorkOrderCancelRequest;
use App\Http\Requests\Api\V1\WorkOrderCompleteRequest;
use App\Http\Requests\Api\V1\WorkOrderRequest;
use App\Http\Resources\Api\V1\WorkOrderResource;
use App\Models\MaintenanceSchedule;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\Maintenance\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkOrderController extends Controller
{
    public function __construct(
        private readonly MaintenanceService $maintenanceService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkOrder::class);

        $workOrders = WorkOrder::query()
            ->with(['maintenanceSchedule', 'maintenanceRequest', 'vehicle', 'serviceProvider', 'assignee', 'maintenanceRecord'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('work_order_number', 'like', $search)
                        ->orWhere('title', 'like', $search)
                        ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('registration_number', 'like', $search));
                });
            })
            ->when($request->filled('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->filled('filter.priority'), fn ($query, $priority) => $query->where('priority', $priority))
            ->when($request->filled('filter.vehicle_id'), fn ($query, $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->orderByRaw("CASE WHEN status IN ('open','in_progress') THEN 0 ELSE 1 END")
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return ApiResponse::success(
            WorkOrderResource::collection($workOrders),
            meta: [
                'current_page' => $workOrders->currentPage(),
                'last_page' => $workOrders->lastPage(),
                'per_page' => $workOrders->perPage(),
                'total' => $workOrders->total(),
            ]
        );
    }

    public function supportData(Request $request): JsonResponse
    {
        if (! $request->user()?->hasAnyPermission(['maintenance.view', 'maintenance.create', 'maintenance.update'])) {
            return ApiResponse::forbidden('You do not have permission to access work order support data.');
        }

        return ApiResponse::success([
            'types' => config('fleet.work_order.types', []),
            'priorities' => config('fleet.work_order.priorities', []),
            'statuses' => config('fleet.work_order.statuses', []),
            'vehicles' => Vehicle::query()
                ->where('status', '!=', 'decommissioned')
                ->orderBy('registration_number')
                ->get(['id', 'registration_number', 'make', 'model', 'status'])
                ->map(fn (Vehicle $vehicle) => [
                    'id' => $vehicle->id,
                    'label' => $vehicle->registration_number,
                    'secondary' => trim($vehicle->make.' '.$vehicle->model),
                    'status' => $vehicle->status,
                ]),
            'schedules' => MaintenanceSchedule::query()
                ->where('status', 'active')
                ->orderBy('title')
                ->get(['id', 'title', 'vehicle_id', 'schedule_type'])
                ->map(fn (MaintenanceSchedule $schedule) => [
                    'id' => $schedule->id,
                    'label' => $schedule->title,
                    'secondary' => $schedule->schedule_type,
                    'vehicle_id' => $schedule->vehicle_id,
                ]),
            'service_providers' => ServiceProvider::query()
                ->where('status', 'active')
                ->whereIn('provider_type', ['garage', 'inspection_center', 'other'])
                ->orderBy('name')
                ->get(['id', 'name', 'provider_type'])
                ->map(fn (ServiceProvider $provider) => [
                    'id' => $provider->id,
                    'label' => $provider->name,
                    'secondary' => $provider->provider_type,
                ]),
            'assignees' => User::query()
                ->visibleTo($request->user())
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'label' => $user->name,
                    'secondary' => $user->email,
                ]),
        ]);
    }

    public function store(WorkOrderRequest $request): JsonResponse
    {
        $this->authorize('create', WorkOrder::class);

        $workOrder = $this->maintenanceService->createWorkOrder($request->validated(), $request->user());

        return ApiResponse::created(new WorkOrderResource($workOrder), 'Work order created successfully.');
    }

    public function show(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('view', $workOrder);

        return ApiResponse::success(
            new WorkOrderResource($workOrder->load(['maintenanceSchedule', 'maintenanceRequest', 'vehicle', 'serviceProvider', 'assignee', 'maintenanceRecord']))
        );
    }

    public function update(WorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('update', $workOrder);

        $workOrder = $this->maintenanceService->updateWorkOrder($workOrder, $request->validated());

        return ApiResponse::success(new WorkOrderResource($workOrder->load(['maintenanceSchedule', 'maintenanceRequest', 'vehicle', 'serviceProvider', 'assignee', 'maintenanceRecord'])), 'Work order updated successfully.');
    }

    public function start(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('start', $workOrder);

        $workOrder = $this->maintenanceService->startWorkOrder($workOrder);

        return ApiResponse::success(new WorkOrderResource($workOrder->load(['maintenanceSchedule', 'maintenanceRequest', 'vehicle', 'serviceProvider', 'assignee', 'maintenanceRecord'])), 'Work order started successfully.');
    }

    public function complete(WorkOrderCompleteRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('complete', $workOrder);

        $workOrder = $this->maintenanceService->completeWorkOrder($workOrder, $request->validated(), $request->user());

        return ApiResponse::success(new WorkOrderResource($workOrder->load(['maintenanceSchedule', 'maintenanceRequest', 'vehicle', 'serviceProvider', 'assignee', 'maintenanceRecord'])), 'Work order completed successfully.');
    }

    public function cancel(WorkOrderCancelRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('cancel', $workOrder);

        $workOrder = $this->maintenanceService->cancelWorkOrder(
            $workOrder,
            $request->string('resolution_notes')->toString() ?: null,
        );

        return ApiResponse::success(new WorkOrderResource($workOrder->load(['maintenanceSchedule', 'maintenanceRequest', 'vehicle', 'serviceProvider', 'assignee', 'maintenanceRecord'])), 'Work order cancelled successfully.');
    }

    public function destroy(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('delete', $workOrder);

        if ($workOrder->status === 'completed') {
            return ApiResponse::error('Completed work orders cannot be deleted.', 422);
        }

        $workOrder->delete();

        return ApiResponse::noContent('Work order deleted successfully.');
    }
}
