<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\AssetDocumentRequest;
use App\Http\Resources\Api\V1\AssetDocumentResource;
use App\Jobs\ScanAssetDocument;
use App\Models\AssetDocument;
use App\Models\Driver;
use App\Models\ServiceProvider;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AssetDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AssetDocument::class);

        $allowedSorts = ['name', 'document_type', 'expiry_date', 'created_at'];
        $sort = in_array($request->input('sort'), $allowedSorts, true) ? $request->input('sort') : 'expiry_date';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $documentableClass = $this->resolveDocumentableClass($request->input('filter.documentable_type'));
        $expiringWithinDays = $request->integer('filter.expiring_within_days');

        $documents = AssetDocument::query()
            ->with('documentable')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'ilike', $search)
                        ->orWhere('document_number', 'ilike', $search)
                        ->orWhere('file_name', 'ilike', $search);
                });
            })
            ->when($request->filled('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->filled('filter.document_type'), fn ($query, $documentType) => $query->where('document_type', $documentType))
            ->when($documentableClass, fn ($query) => $query->where('documentable_type', $documentableClass))
            ->when($request->filled('filter.documentable_id'), fn ($query, $documentableId) => $query->where('documentable_id', $documentableId))
            ->when($expiringWithinDays > 0, function ($query) use ($expiringWithinDays) {
                $query
                    ->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', '>=', now()->toDateString())
                    ->whereDate('expiry_date', '<=', now()->addDays($expiringWithinDays)->toDateString());
            })
            ->orderBy($sort, $direction)
            ->paginate($this->perPage($request, 15));

        return ApiResponse::success(
            AssetDocumentResource::collection($documents),
            meta: [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ]
        );
    }

    public function supportData(Request $request): JsonResponse
    {
        if (! $request->user()?->hasAnyPermission(['documents.view', 'documents.create', 'documents.update'])) {
            return ApiResponse::forbidden('You do not have permission to access document support data.');
        }

        return ApiResponse::success([
            'vehicles' => Vehicle::query()
                ->orderBy('registration_number')
                ->limit(50)
                ->get(['id', 'registration_number', 'make', 'model'])
                ->map(fn (Vehicle $vehicle) => [
                    'id' => $vehicle->id,
                    'label' => $vehicle->registration_number,
                    'secondary' => trim($vehicle->make.' '.$vehicle->model),
                ]),
            'drivers' => Driver::query()
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'license_number'])
                ->map(fn (Driver $driver) => [
                    'id' => $driver->id,
                    'label' => $driver->name,
                    'secondary' => $driver->license_number,
                ]),
            'service_providers' => ServiceProvider::query()
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'provider_type'])
                ->map(fn (ServiceProvider $serviceProvider) => [
                    'id' => $serviceProvider->id,
                    'label' => $serviceProvider->name,
                    'secondary' => $serviceProvider->provider_type,
                ]),
        ]);
    }

    public function typeahead(Request $request): JsonResponse
    {
        if (! $request->user()?->hasAnyPermission(['documents.view', 'documents.create', 'documents.update'])) {
            return ApiResponse::forbidden('You do not have permission to access document support data.');
        }

        $request->validate([
            'type' => ['required', 'string', 'in:vehicle,driver,service_provider'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = '%'.$request->string('search')->trim().'%';
        $type = $request->string('type')->toString();

        $results = match ($type) {
            'vehicle' => Vehicle::query()
                ->when($request->filled('search'), function ($query) use ($search) {
                    $query->where('registration_number', 'ilike', $search)
                        ->orWhere('make', 'ilike', $search)
                        ->orWhere('model', 'ilike', $search);
                })
                ->orderBy('registration_number')
                ->paginate($this->perPage($request, 15)),
            'driver' => Driver::query()
                ->when($request->filled('search'), function ($query) use ($search) {
                    $query->where('name', 'ilike', $search)
                        ->orWhere('license_number', 'ilike', $search);
                })
                ->orderBy('name')
                ->paginate($this->perPage($request, 15)),
            'service_provider' => ServiceProvider::query()
                ->when($request->filled('search'), function ($query) use ($search) {
                    $query->where('name', 'ilike', $search)
                        ->orWhere('provider_type', 'ilike', $search);
                })
                ->orderBy('name')
                ->paginate($this->perPage($request, 15)),
        };

        $items = $results->getCollection()->map(fn ($item) => match ($type) {
            'vehicle' => [
                'id' => $item->id,
                'label' => $item->registration_number,
                'secondary' => trim($item->make.' '.$item->model),
            ],
            'driver' => [
                'id' => $item->id,
                'label' => $item->name,
                'secondary' => $item->license_number,
            ],
            'service_provider' => [
                'id' => $item->id,
                'label' => $item->name,
                'secondary' => $item->provider_type,
            ],
        });

        return ApiResponse::success($items, meta: [
            'current_page' => $results->currentPage(),
            'last_page' => $results->lastPage(),
            'per_page' => $results->perPage(),
            'total' => $results->total(),
        ]);
    }

    public function store(AssetDocumentRequest $request): JsonResponse
    {
        $this->authorize('create', AssetDocument::class);

        $fileAttributes = null;
        $payload = $this->payloadFromRequest($request);

        try {
            $document = DB::transaction(function () use ($request, $payload, &$fileAttributes) {
                if ($request->hasFile('file')) {
                    $fileAttributes = $this->storeUploadedFile(
                        $request->file('file'),
                        $payload['documentable_type'],
                        $payload['documentable_id'],
                        $request->user()?->tenant_id
                    );
                }

                return AssetDocument::create(array_merge($payload, $fileAttributes ?? []));
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFile($fileAttributes);

            throw $exception;
        }

        if ($fileAttributes) {
            ScanAssetDocument::dispatch($document->id);
        }

        return ApiResponse::created(
            new AssetDocumentResource($document->load('documentable')),
            'Asset document created successfully.'
        );
    }

    public function show(AssetDocument $assetDocument): JsonResponse
    {
        $this->authorize('view', $assetDocument);

        return ApiResponse::success(new AssetDocumentResource($assetDocument->load('documentable')));
    }

    public function update(AssetDocumentRequest $request, AssetDocument $assetDocument): JsonResponse
    {
        $this->authorize('update', $assetDocument);

        $newFileAttributes = null;
        $oldFileAttributes = [
            'storage_disk' => $assetDocument->storage_disk,
            'file_path' => $assetDocument->file_path,
        ];
        $payload = $this->payloadFromRequest($request);

        try {
            DB::transaction(function () use ($request, $assetDocument, $payload, &$newFileAttributes) {
                if ($request->hasFile('file')) {
                    $newFileAttributes = $this->storeUploadedFile(
                        $request->file('file'),
                        $payload['documentable_type'],
                        $payload['documentable_id'],
                        $request->user()?->tenant_id
                    );
                }

                $assetDocument->update(array_merge($payload, $newFileAttributes ?? []));
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFile($newFileAttributes);

            throw $exception;
        }

        if ($newFileAttributes && ! empty($oldFileAttributes['file_path'])) {
            Storage::disk($oldFileAttributes['storage_disk'] ?: config('filesystems.default'))
                ->delete($oldFileAttributes['file_path']);
        }

        if ($newFileAttributes) {
            ScanAssetDocument::dispatch($assetDocument->id);
        }

        return ApiResponse::success(
            new AssetDocumentResource($assetDocument->fresh()->load('documentable')),
            'Asset document updated successfully.'
        );
    }

    public function destroy(AssetDocument $assetDocument): JsonResponse
    {
        $this->authorize('delete', $assetDocument);

        $assetDocument->delete();

        return ApiResponse::noContent('Asset document deleted successfully.');
    }

    public function download(AssetDocument $assetDocument): StreamedResponse|JsonResponse
    {
        $this->authorize('view', $assetDocument);

        if (! $assetDocument->file_path || ! Storage::disk($assetDocument->storage_disk)->exists($assetDocument->file_path)) {
            return ApiResponse::notFound('The requested file could not be found.');
        }

        if ($assetDocument->scan_status !== 'clean') {
            return ApiResponse::error('This file is not available until malware scanning completes successfully.', 409);
        }

        return Storage::disk($assetDocument->storage_disk)->download(
            $assetDocument->file_path,
            $this->downloadFileName($assetDocument),
            [
                'Content-Type' => $assetDocument->mime_type ?? 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function payloadFromRequest(AssetDocumentRequest $request): array
    {
        $payload = $request->safe()->except('file');
        $payload['documentable_type'] = AssetDocument::resolveDocumentableClass($payload['documentable_type']);

        return $payload;
    }

    private function resolveDocumentableClass(?string $documentableType): ?string
    {
        if (! $documentableType) {
            return null;
        }

        return AssetDocument::resolveDocumentableClass($documentableType);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function storeUploadedFile(
        UploadedFile $file,
        string $documentableType,
        int $documentableId,
        ?int $tenantId
    ): array {
        $disk = config('filesystems.default');
        $extension = $file->getClientOriginalExtension();
        $sanitizedType = Str::snake(class_basename($documentableType));
        $storedName = Str::uuid().($extension ? '.'.$extension : '');
        $path = sprintf(
            'tenants/%s/documents/%s/%s/%s',
            $tenantId ?? 'shared',
            $sanitizedType,
            $documentableId,
            $storedName
        );

        Storage::disk($disk)->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        return [
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'storage_disk' => $disk,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'scan_status' => 'pending',
            'scanned_at' => null,
            'scan_error' => null,
        ];
    }

    private function downloadFileName(AssetDocument $assetDocument): string
    {
        $extension = pathinfo((string) $assetDocument->file_path, PATHINFO_EXTENSION);
        $baseName = 'asset-document-'.$assetDocument->id;

        return $extension ? $baseName.'.'.$extension : $baseName;
    }

    /**
     * @param  array<string, mixed>|null  $fileAttributes
     */
    private function deleteStoredFile(?array $fileAttributes): void
    {
        if (! $fileAttributes || empty($fileAttributes['file_path'])) {
            return;
        }

        Storage::disk($fileAttributes['storage_disk'] ?? config('filesystems.default'))
            ->delete($fileAttributes['file_path']);
    }
}
