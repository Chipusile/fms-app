<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\TripApproveRequest;
use App\Http\Requests\Api\V1\TripCancelRequest;
use App\Http\Requests\Api\V1\TripCompleteRequest;
use App\Http\Requests\Api\V1\TripRejectRequest;
use App\Http\Requests\Api\V1\TripRequest;
use App\Http\Requests\Api\V1\TripStartRequest;
use App\Http\Resources\Api\V1\TripResource;
use App\Models\Driver;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\Operations\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function __construct(
        private readonly TripService $tripService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Trip::class);

        $allowedSorts = ['scheduled_start', 'scheduled_end', 'created_at', 'trip_number', 'status'];
        $sort = in_array($request->input('sort'), $allowedSorts, true) ? $request->input('sort') : 'scheduled_start';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $trips = Trip::query()
            ->with(['vehicle', 'driver', 'requester', 'approver'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('trip_number', 'like', $search)
                        ->orWhere('purpose', 'like', $search)
                        ->orWhere('origin', 'like', $search)
                        ->orWhere('destination', 'like', $search)
                        ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('registration_number', 'like', $search))
                        ->orWhereHas('driver', fn ($driverQuery) => $driverQuery->where('name', 'like', $search));
                });
            })
            ->when($request->filled('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->filled('filter.vehicle_id'), fn ($query, $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($request->filled('filter.driver_id'), fn ($query, $driverId) => $query->where('driver_id', $driverId))
            ->when($request->filled('filter.requested_by'), fn ($query, $requestedBy) => $query->where('requested_by', $requestedBy))
            ->orderBy($sort, $direction)
            ->paginate($request->input('per_page', 15));

        return ApiResponse::success(
            TripResource::collection($trips),
            meta: [
                'current_page' => $trips->currentPage(),
                'last_page' => $trips->lastPage(),
                'per_page' => $trips->perPage(),
                'total' => $trips->total(),
            ]
        );
    }

    public function supportData(Request $request): JsonResponse
    {
        if (! $request->user()?->hasAnyPermission(['trips.view', 'trips.create', 'trips.update', 'trips.approve'])) {
            return ApiResponse::forbidden('You do not have permission to access trip support data.');
        }

        return ApiResponse::success([
            'trip_statuses' => config('fleet.trip.statuses', []),
            'trip_approval_required' => (bool) Setting::getValue('approvals.trip_approval_required', true),
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
        ]);
    }

    public function store(TripRequest $request): JsonResponse
    {
        $this->authorize('create', Trip::class);

        $trip = $this->tripService->create($request->validated(), $request->user());

        return ApiResponse::created(new TripResource($trip), 'Trip created successfully.');
    }

    public function show(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        return ApiResponse::success(new TripResource($trip->load(['vehicle', 'driver', 'requester', 'approver'])));
    }

    public function update(TripRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $trip = $this->tripService->update($trip, $request->validated());

        return ApiResponse::success(new TripResource($trip), 'Trip updated successfully.');
    }

    public function approve(TripApproveRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('approve', $trip);

        $trip = $this->tripService->approve($trip, $request->user(), $request->string('notes')->toString() ?: null);

        return ApiResponse::success(new TripResource($trip), 'Trip approved successfully.');
    }

    public function reject(TripRejectRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('reject', $trip);

        $trip = $this->tripService->reject($trip, $request->user(), $request->string('reason')->toString());

        return ApiResponse::success(new TripResource($trip), 'Trip rejected successfully.');
    }

    public function start(TripStartRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('start', $trip);

        $trip = $this->tripService->start(
            $trip,
            $request->user(),
            $request->integer('start_odometer'),
            $request->input('actual_start'),
        );

        return ApiResponse::success(new TripResource($trip), 'Trip started successfully.');
    }

    public function complete(TripCompleteRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('complete', $trip);

        $trip = $this->tripService->complete(
            $trip,
            $request->user(),
            $request->integer('end_odometer'),
            $request->input('actual_end'),
            $request->string('notes')->toString() ?: null,
        );

        return ApiResponse::success(new TripResource($trip), 'Trip completed successfully.');
    }

    public function cancel(TripCancelRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('cancel', $trip);

        $trip = $this->tripService->cancel($trip, $request->string('reason')->toString());

        return ApiResponse::success(new TripResource($trip), 'Trip cancelled successfully.');
    }
}
