<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\ServiceProviderRequest;
use App\Http\Resources\Api\V1\ServiceProviderResource;
use App\Models\ServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceProvider::class);

        $allowedSorts = ['name', 'provider_type', 'created_at'];
        $sort = in_array($request->input('sort'), $allowedSorts, true) ? $request->input('sort') : 'name';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $serviceProviders = ServiceProvider::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->input('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('filter.provider_type'), fn ($query, $providerType) => $query->where('provider_type', $providerType))
            ->orderBy($sort, $direction)
            ->paginate($request->input('per_page', 15));

        return ApiResponse::success(
            ServiceProviderResource::collection($serviceProviders),
            meta: [
                'current_page' => $serviceProviders->currentPage(),
                'last_page' => $serviceProviders->lastPage(),
                'per_page' => $serviceProviders->perPage(),
                'total' => $serviceProviders->total(),
            ]
        );
    }

    public function store(ServiceProviderRequest $request): JsonResponse
    {
        $this->authorize('create', ServiceProvider::class);

        $serviceProvider = ServiceProvider::create($request->validated());

        return ApiResponse::created(new ServiceProviderResource($serviceProvider));
    }

    public function show(ServiceProvider $serviceProvider): JsonResponse
    {
        $this->authorize('view', $serviceProvider);

        return ApiResponse::success(new ServiceProviderResource($serviceProvider));
    }

    public function update(ServiceProviderRequest $request, ServiceProvider $serviceProvider): JsonResponse
    {
        $this->authorize('update', $serviceProvider);

        $serviceProvider->update($request->validated());

        return ApiResponse::success(new ServiceProviderResource($serviceProvider->fresh()), 'Service provider updated successfully.');
    }

    public function destroy(ServiceProvider $serviceProvider): JsonResponse
    {
        $this->authorize('delete', $serviceProvider);

        $serviceProvider->delete();

        return ApiResponse::noContent('Service provider deleted successfully.');
    }
}
