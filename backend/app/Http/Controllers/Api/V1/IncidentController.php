<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\IncidentRequest;
use App\Http\Requests\Api\V1\IncidentResolveRequest;
use App\Http\Resources\Api\V1\IncidentResource;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Operations\IncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function __construct(
        private readonly IncidentService $incidentService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Incident::class);

        $incidents = Incident::query()
            ->with(['vehicle', 'driver', 'trip', 'reporter', 'assignee'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('incident_number', 'like', $search)
                        ->orWhere('description', 'like', $search)
                        ->orWhere('location', 'like', $search)
                        ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('registration_number', 'like', $search));
                });
            })
            ->when($request->filled('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->filled('filter.severity'), fn ($query, $severity) => $query->where('severity', $severity))
            ->when($request->filled('filter.incident_type'), fn ($query, $type) => $query->where('incident_type', $type))
            ->when($request->filled('filter.vehicle_id'), fn ($query, $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->orderByDesc('occurred_at')
            ->paginate($request->input('per_page', 15));

        return ApiResponse::success(
            IncidentResource::collection($incidents),
            meta: [
                'current_page' => $incidents->currentPage(),
                'last_page' => $incidents->lastPage(),
                'per_page' => $incidents->perPage(),
                'total' => $incidents->total(),
            ]
        );
    }

    public function supportData(Request $request): JsonResponse
    {
        if (! $request->user()?->hasAnyPermission(['incidents.view', 'incidents.create', 'incidents.update'])) {
            return ApiResponse::forbidden('You do not have permission to access incident support data.');
        }

        return ApiResponse::success([
            'types' => config('fleet.incident.types', []),
            'severities' => config('fleet.incident.severities', []),
            'statuses' => config('fleet.incident.statuses', []),
            'vehicles' => Vehicle::query()
                ->orderBy('registration_number')
                ->get(['id', 'registration_number', 'make', 'model'])
                ->map(fn (Vehicle $vehicle) => [
                    'id' => $vehicle->id,
                    'label' => $vehicle->registration_number,
                    'secondary' => trim($vehicle->make.' '.$vehicle->model),
                ]),
            'drivers' => Driver::query()
                ->orderBy('name')
                ->get(['id', 'name', 'license_number'])
                ->map(fn (Driver $driver) => [
                    'id' => $driver->id,
                    'label' => $driver->name,
                    'secondary' => $driver->license_number,
                ]),
            'trips' => Trip::query()
                ->orderByDesc('scheduled_start')
                ->get(['id', 'trip_number', 'vehicle_id', 'driver_id', 'status'])
                ->map(fn (Trip $trip) => [
                    'id' => $trip->id,
                    'label' => $trip->trip_number,
                    'secondary' => $trip->status,
                    'vehicle_id' => $trip->vehicle_id,
                    'driver_id' => $trip->driver_id,
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

    public function store(IncidentRequest $request): JsonResponse
    {
        $this->authorize('create', Incident::class);

        $incident = $this->incidentService->create($request->validated(), $request->user());

        return ApiResponse::created(new IncidentResource($incident), 'Incident reported successfully.');
    }

    public function show(Incident $incident): JsonResponse
    {
        $this->authorize('view', $incident);

        return ApiResponse::success(
            new IncidentResource($incident->load([
                'vehicle',
                'driver',
                'trip',
                'reporter',
                'assignee',
                'approvalRequests.requester',
                'approvalRequests.decider',
                'approvalRequests.approvalable',
            ]))
        );
    }

    public function update(IncidentRequest $request, Incident $incident): JsonResponse
    {
        $this->authorize('update', $incident);

        $incident = $this->incidentService->update($incident, $request->validated());

        return ApiResponse::success(new IncidentResource($incident), 'Incident updated successfully.');
    }

    public function resolve(IncidentResolveRequest $request, Incident $incident): JsonResponse
    {
        $this->authorize('resolve', $incident);

        $incident = $this->incidentService->resolve(
            $incident,
            $request->string('resolution_notes')->toString(),
        );

        return ApiResponse::success(new IncidentResource($incident), 'Incident resolved successfully.');
    }

    public function close(Incident $incident): JsonResponse
    {
        $this->authorize('close', $incident);

        $incident = $this->incidentService->close($incident);

        return ApiResponse::success(new IncidentResource($incident), 'Incident closed successfully.');
    }

    public function destroy(Incident $incident): JsonResponse
    {
        $this->authorize('delete', $incident);

        $incident->delete();

        return ApiResponse::noContent('Incident deleted successfully.');
    }
}
