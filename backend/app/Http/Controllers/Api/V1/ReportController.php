<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\DashboardQueryRequest;
use App\Http\Requests\Api\V1\ReportQueryRequest;
use App\Services\Reporting\DashboardAnalyticsService;
use App\Services\Reporting\ReportQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly DashboardAnalyticsService $dashboardAnalyticsService,
        private readonly ReportQueryService $reportQueryService,
    ) {
    }

    public function supportData(Request $request): JsonResponse
    {
        if (! $request->user()?->hasPermission('reports.view')) {
            return ApiResponse::forbidden('You do not have permission to access report support data.');
        }

        return ApiResponse::success(
            $this->reportQueryService->supportData($request->user()->tenant_id)
        );
    }

    public function dashboard(DashboardQueryRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->dashboardAnalyticsService->dashboard(
                $request->user()->tenant_id,
                $request->validated(),
            )
        );
    }

    public function fleetOverview(ReportQueryRequest $request): JsonResponse
    {
        return $this->respondWithReport('fleet-overview', $request);
    }

    public function vehicleUtilization(ReportQueryRequest $request): JsonResponse
    {
        return $this->respondWithReport('vehicle-utilization', $request);
    }

    public function fuelConsumption(ReportQueryRequest $request): JsonResponse
    {
        return $this->respondWithReport('fuel-consumption', $request);
    }

    public function maintenanceCost(ReportQueryRequest $request): JsonResponse
    {
        return $this->respondWithReport('maintenance-cost', $request);
    }

    public function complianceStatus(ReportQueryRequest $request): JsonResponse
    {
        return $this->respondWithReport('compliance-status', $request);
    }

    public function incidentSummary(ReportQueryRequest $request): JsonResponse
    {
        return $this->respondWithReport('incident-summary', $request);
    }

    private function respondWithReport(string $reportType, ReportQueryRequest $request): JsonResponse
    {
        $report = $this->reportQueryService->dataset(
            $reportType,
            $request->user()->tenant_id,
            $request->validated(),
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 15),
        );

        $meta = $report['meta'];
        unset($report['meta']);

        return ApiResponse::success($report, meta: $meta);
    }
}
