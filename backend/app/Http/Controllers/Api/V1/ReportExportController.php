<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\ReportExportRequest;
use App\Http\Resources\Api\V1\ReportExportResource;
use App\Models\ReportExport;
use App\Services\Reporting\ReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __construct(
        private readonly ReportExportService $reportExportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReportExport::class);

        $exports = ReportExport::query()
            ->with('requester')
            ->when(! $request->user()->hasPermission('reports.view-all'), function ($query) use ($request) {
                $query->where('requested_by', $request->user()->id);
            })
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request, 10));

        return ApiResponse::success(
            ReportExportResource::collection($exports),
            meta: [
                'current_page' => $exports->currentPage(),
                'last_page' => $exports->lastPage(),
                'per_page' => $exports->perPage(),
                'total' => $exports->total(),
            ],
        );
    }

    public function store(ReportExportRequest $request): JsonResponse
    {
        $this->authorize('create', ReportExport::class);

        $reportExport = $this->reportExportService->queueExport($request->user(), $request->validated());

        return ApiResponse::created(
            new ReportExportResource($reportExport),
            'Report export queued successfully.',
        );
    }

    public function show(ReportExport $reportExport): JsonResponse
    {
        $this->authorize('view', $reportExport);

        return ApiResponse::success(
            new ReportExportResource($reportExport->load('requester'))
        );
    }

    public function download(ReportExport $reportExport): StreamedResponse|JsonResponse
    {
        $this->authorize('download', $reportExport);

        if ($reportExport->status !== 'completed' || ! $reportExport->file_path || ! $reportExport->storage_disk) {
            return ApiResponse::error('This export is not ready for download.', 422);
        }

        return $this->reportExportService->downloadResponse($reportExport);
    }
}
