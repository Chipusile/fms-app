<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\InspectionCloseRequest;
use App\Http\Requests\Api\V1\InspectionRequest;
use App\Http\Resources\Api\V1\InspectionResource;
use App\Http\Resources\Api\V1\InspectionTemplateResource;
use App\Models\Driver;
use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\Operations\InspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectionController extends Controller
{
    public function __construct(
        private readonly InspectionService $inspectionService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Inspection::class);

        $inspections = Inspection::query()
            ->with(['template', 'vehicle', 'driver', 'trip', 'inspector'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('inspection_number', 'like', $search)
                        ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('registration_number', 'like', $search))
                        ->orWhereHas('template', fn ($templateQuery) => $templateQuery->where('name', 'like', $search));
                });
            })
            ->when($request->filled('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->filled('filter.result'), fn ($query, $result) => $query->where('result', $result))
            ->when($request->filled('filter.vehicle_id'), fn ($query, $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->orderByDesc('performed_at')
            ->paginate($request->input('per_page', 15));

        return ApiResponse::success(
            InspectionResource::collection($inspections),
            meta: [
                'current_page' => $inspections->currentPage(),
                'last_page' => $inspections->lastPage(),
                'per_page' => $inspections->perPage(),
                'total' => $inspections->total(),
            ]
        );
    }

    public function supportData(Request $request): JsonResponse
    {
        if (! $request->user()?->hasAnyPermission(['inspections.view', 'inspections.create', 'inspections.update', 'inspection-templates.view'])) {
            return ApiResponse::forbidden('You do not have permission to access inspection support data.');
        }

        return ApiResponse::success([
            'statuses' => config('fleet.inspection.statuses', []),
            'results' => config('fleet.inspection.results', []),
            'defect_severities' => config('fleet.inspection.defect_severities', []),
            'templates' => InspectionTemplateResource::collection(
                InspectionTemplate::query()
                    ->where('status', 'active')
                    ->with('items')
                    ->orderBy('name')
                    ->get()
            ),
            'vehicles' => Vehicle::query()
                ->where('status', 'active')
                ->orderBy('registration_number')
                ->get(['id', 'registration_number', 'make', 'model', 'odometer_reading'])
                ->map(fn (Vehicle $vehicle) => [
                    'id' => $vehicle->id,
                    'label' => $vehicle->registration_number,
                    'secondary' => trim($vehicle->make.' '.$vehicle->model),
                    'odometer_reading' => $vehicle->odometer_reading,
                ]),
            'drivers' => Driver::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'license_number'])
                ->map(fn (Driver $driver) => [
                    'id' => $driver->id,
                    'label' => $driver->name,
                    'secondary' => $driver->license_number,
                ]),
            'trips' => Trip::query()
                ->whereIn('status', ['approved', 'in_progress', 'completed'])
                ->orderByDesc('scheduled_start')
                ->get(['id', 'trip_number', 'vehicle_id', 'driver_id', 'status'])
                ->map(fn (Trip $trip) => [
                    'id' => $trip->id,
                    'label' => $trip->trip_number,
                    'secondary' => $trip->status,
                    'vehicle_id' => $trip->vehicle_id,
                    'driver_id' => $trip->driver_id,
                ]),
        ]);
    }

    public function store(InspectionRequest $request): JsonResponse
    {
        $this->authorize('create', Inspection::class);

        $inspection = $this->inspectionService->create($request->validated(), $request->user());

        return ApiResponse::created(new InspectionResource($inspection), 'Inspection recorded successfully.');
    }

    public function show(Inspection $inspection): JsonResponse
    {
        $this->authorize('view', $inspection);

        return ApiResponse::success(
            new InspectionResource($inspection->load([
                'template.items',
                'vehicle',
                'driver',
                'trip',
                'inspector',
                'responses',
                'approvalRequests.requester',
                'approvalRequests.decider',
                'approvalRequests.approvalable',
            ]))
        );
    }

    public function close(InspectionCloseRequest $request, Inspection $inspection): JsonResponse
    {
        $this->authorize('close', $inspection);

        $inspection = $this->inspectionService->close(
            $inspection,
            $request->string('resolution_notes')->toString() ?: null,
        );

        return ApiResponse::success(new InspectionResource($inspection), 'Inspection closed successfully.');
    }
}
