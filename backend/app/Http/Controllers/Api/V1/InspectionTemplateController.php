<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\InspectionTemplateRequest;
use App\Http\Resources\Api\V1\InspectionTemplateResource;
use App\Models\InspectionTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InspectionTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InspectionTemplate::class);

        $templates = InspectionTemplate::query()
            ->with('items')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'like', $search)
                        ->orWhere('code', 'like', $search);
                });
            })
            ->when($request->filled('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate($request->input('per_page', 15));

        return ApiResponse::success(
            InspectionTemplateResource::collection($templates),
            meta: [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
            ]
        );
    }

    public function store(InspectionTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', InspectionTemplate::class);

        $template = DB::transaction(function () use ($request) {
            $template = InspectionTemplate::create($request->safe()->except('items'));
            $this->syncItems($template, $request->input('items', []));

            return $template;
        });

        return ApiResponse::created(
            new InspectionTemplateResource($template->load('items')),
            'Inspection template created successfully.'
        );
    }

    public function show(InspectionTemplate $inspectionTemplate): JsonResponse
    {
        $this->authorize('view', $inspectionTemplate);

        return ApiResponse::success(new InspectionTemplateResource($inspectionTemplate->load('items')));
    }

    public function update(InspectionTemplateRequest $request, InspectionTemplate $inspectionTemplate): JsonResponse
    {
        $this->authorize('update', $inspectionTemplate);

        DB::transaction(function () use ($request, $inspectionTemplate) {
            $inspectionTemplate->update($request->safe()->except('items'));
            $inspectionTemplate->items()->delete();
            $this->syncItems($inspectionTemplate, $request->input('items', []));
        });

        return ApiResponse::success(
            new InspectionTemplateResource($inspectionTemplate->fresh()->load('items')),
            'Inspection template updated successfully.'
        );
    }

    public function destroy(InspectionTemplate $inspectionTemplate): JsonResponse
    {
        $this->authorize('delete', $inspectionTemplate);

        if ($inspectionTemplate->inspections()->exists()) {
            return ApiResponse::error('Inspection templates with recorded inspections cannot be deleted.', 422);
        }

        $inspectionTemplate->delete();

        return ApiResponse::noContent('Inspection template deleted successfully.');
    }

    private function syncItems(InspectionTemplate $inspectionTemplate, array $items): void
    {
        foreach ($items as $index => $item) {
            $inspectionTemplate->items()->create([
                'tenant_id' => $inspectionTemplate->tenant_id,
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
                'response_type' => $item['response_type'],
                'is_required' => $item['is_required'],
                'triggers_defect_on_fail' => $item['triggers_defect_on_fail'],
                'sort_order' => $item['sort_order'] ?? ($index + 1),
            ]);
        }
    }
}
