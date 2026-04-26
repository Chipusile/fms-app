<?php

use App\Http\Controllers\Api\V1\ApprovalRequestController;
use App\Http\Controllers\Api\V1\AssetDocumentController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ComplianceItemController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\FuelLogController;
use App\Http\Controllers\Api\V1\ImportTemplateController;
use App\Http\Controllers\Api\V1\IncidentController;
use App\Http\Controllers\Api\V1\InspectionController;
use App\Http\Controllers\Api\V1\InspectionTemplateController;
use App\Http\Controllers\Api\V1\MaintenanceRequestController;
use App\Http\Controllers\Api\V1\MaintenanceScheduleController;
use App\Http\Controllers\Api\V1\OdometerReadingController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReportExportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\ServiceProviderController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserNotificationController;
use App\Http\Controllers\Api\V1\VehicleAssignmentController;
use App\Http\Controllers\Api\V1\VehicleComponentController;
use App\Http\Controllers\Api\V1\VehicleController;
use App\Http\Controllers\Api\V1\VehicleTypeController;
use App\Http\Controllers\Api\V1\WorkOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1 via RouteServiceProvider.
| Auth routes use Sanctum SPA authentication (cookie-based).
| Protected routes require authentication and an active tenant.
|
*/

Route::prefix('v1')->group(function () {

    // Public auth routes
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth-login');
    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:6,1');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:6,1');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:6,1');
    Route::post('auth/invitations/accept', [AuthController::class, 'acceptInvitation'])
        ->middleware('throttle:6,1');
    Route::get('auth/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Protected routes
    Route::middleware(['auth:sanctum', 'tenant.active', 'subscription.active'])->group(function () {

        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/email/verification-notification', [AuthController::class, 'sendEmailVerification'])
            ->middleware('throttle:6,1');

        // Tenants (Super Admin only)
        Route::apiResource('tenants', TenantController::class);

        // Users
        Route::apiResource('users', UserController::class);

        // Roles
        Route::apiResource('roles', RoleController::class);

        // Fleet master data
        Route::apiResource('vehicle-types', VehicleTypeController::class);
        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('drivers', DriverController::class);
        Route::apiResource('service-providers', ServiceProviderController::class);
        Route::apiResource('vehicles', VehicleController::class);
        Route::get('vehicle-assignments/support-data', [VehicleAssignmentController::class, 'supportData']);
        Route::apiResource('vehicle-assignments', VehicleAssignmentController::class);
        Route::get('asset-documents/typeahead', [AssetDocumentController::class, 'typeahead']);
        Route::get('asset-documents/support-data', [AssetDocumentController::class, 'supportData']);
        Route::get('asset-documents/{assetDocument}/download', [AssetDocumentController::class, 'download'])
            ->middleware(['signed', 'throttle:asset-downloads'])
            ->name('asset-documents.download');
        Route::apiResource('asset-documents', AssetDocumentController::class);
        Route::get('import-templates', [ImportTemplateController::class, 'index']);
        Route::apiResource('inspection-templates', InspectionTemplateController::class);
        Route::get('inspections/support-data', [InspectionController::class, 'supportData']);
        Route::get('inspections', [InspectionController::class, 'index']);
        Route::post('inspections', [InspectionController::class, 'store']);
        Route::get('inspections/{inspection}', [InspectionController::class, 'show']);
        Route::put('inspections/{inspection}/close', [InspectionController::class, 'close']);
        Route::get('incidents/support-data', [IncidentController::class, 'supportData']);
        Route::put('incidents/{incident}/resolve', [IncidentController::class, 'resolve']);
        Route::put('incidents/{incident}/close', [IncidentController::class, 'close']);
        Route::apiResource('incidents', IncidentController::class);
        Route::put('approvals/{approvalRequest}/approve', [ApprovalRequestController::class, 'approve']);
        Route::put('approvals/{approvalRequest}/reject', [ApprovalRequestController::class, 'reject']);
        Route::get('approvals/{approvalRequest}', [ApprovalRequestController::class, 'show']);
        Route::get('approvals', [ApprovalRequestController::class, 'index']);
        Route::get('notifications', [UserNotificationController::class, 'index']);
        Route::put('notifications/{userNotification}/mark-read', [UserNotificationController::class, 'markRead']);
        Route::put('notifications/{userNotification}/acknowledge', [UserNotificationController::class, 'acknowledge']);
        Route::get('reports/support-data', [ReportController::class, 'supportData'])
            ->middleware('permission:reports.view');
        Route::get('reports/dashboard', [ReportController::class, 'dashboard'])
            ->middleware('permission:reports.view');
        Route::get('reports/fleet-overview', [ReportController::class, 'fleetOverview'])
            ->middleware('permission:reports.view');
        Route::get('reports/vehicle-utilization', [ReportController::class, 'vehicleUtilization'])
            ->middleware('permission:reports.view');
        Route::get('reports/fuel-consumption', [ReportController::class, 'fuelConsumption'])
            ->middleware('permission:reports.view');
        Route::get('reports/maintenance-cost', [ReportController::class, 'maintenanceCost'])
            ->middleware('permission:reports.view');
        Route::get('reports/compliance-status', [ReportController::class, 'complianceStatus'])
            ->middleware('permission:reports.view');
        Route::get('reports/incident-summary', [ReportController::class, 'incidentSummary'])
            ->middleware('permission:reports.view');
        Route::get('reports/exports', [ReportExportController::class, 'index'])
            ->middleware('permission:reports.view');
        Route::post('reports/exports', [ReportExportController::class, 'store'])
            ->middleware(['permission:reports.export', 'throttle:report-exports']);
        Route::get('reports/exports/{reportExport}', [ReportExportController::class, 'show'])
            ->middleware('permission:reports.view');
        Route::get('reports/exports/{reportExport}/download', [ReportExportController::class, 'download'])
            ->middleware(['permission:reports.export', 'throttle:asset-downloads'])
            ->name('reports.exports.download');
        Route::get('maintenance-schedules/support-data', [MaintenanceScheduleController::class, 'supportData']);
        Route::get('maintenance-schedules/upcoming', [MaintenanceScheduleController::class, 'upcoming']);
        Route::get('maintenance-schedules/overdue', [MaintenanceScheduleController::class, 'overdue']);
        Route::apiResource('maintenance-schedules', MaintenanceScheduleController::class)
            ->parameters(['maintenance-schedules' => 'maintenanceSchedule']);
        Route::get('maintenance-requests/support-data', [MaintenanceRequestController::class, 'supportData']);
        Route::put('maintenance-requests/{maintenanceRequest}/approve', [MaintenanceRequestController::class, 'approve']);
        Route::put('maintenance-requests/{maintenanceRequest}/reject', [MaintenanceRequestController::class, 'reject']);
        Route::put('maintenance-requests/{maintenanceRequest}/cancel', [MaintenanceRequestController::class, 'cancel']);
        Route::put('maintenance-requests/{maintenanceRequest}/convert', [MaintenanceRequestController::class, 'convert']);
        Route::apiResource('maintenance-requests', MaintenanceRequestController::class)
            ->parameters(['maintenance-requests' => 'maintenanceRequest']);
        Route::get('work-orders/support-data', [WorkOrderController::class, 'supportData']);
        Route::put('work-orders/{workOrder}/start', [WorkOrderController::class, 'start']);
        Route::put('work-orders/{workOrder}/complete', [WorkOrderController::class, 'complete']);
        Route::put('work-orders/{workOrder}/cancel', [WorkOrderController::class, 'cancel']);
        Route::apiResource('work-orders', WorkOrderController::class)
            ->parameters(['work-orders' => 'workOrder']);
        Route::get('vehicle-components/support-data', [VehicleComponentController::class, 'supportData']);
        Route::get('vehicle-components/due-soon', [VehicleComponentController::class, 'dueSoon']);
        Route::get('vehicle-components/overdue', [VehicleComponentController::class, 'overdue']);
        Route::put('vehicle-components/{vehicleComponent}/retire', [VehicleComponentController::class, 'retire']);
        Route::apiResource('vehicle-components', VehicleComponentController::class)
            ->parameters(['vehicle-components' => 'vehicleComponent']);
        Route::get('compliance-items/support-data', [ComplianceItemController::class, 'supportData']);
        Route::get('compliance-items/dashboard', [ComplianceItemController::class, 'dashboard']);
        Route::get('compliance-items/expiring', [ComplianceItemController::class, 'expiring']);
        Route::apiResource('compliance-items', ComplianceItemController::class)
            ->parameters(['compliance-items' => 'complianceItem']);
        Route::get('trips/support-data', [TripController::class, 'supportData']);
        Route::put('trips/{trip}/approve', [TripController::class, 'approve']);
        Route::put('trips/{trip}/reject', [TripController::class, 'reject']);
        Route::put('trips/{trip}/start', [TripController::class, 'start']);
        Route::put('trips/{trip}/complete', [TripController::class, 'complete']);
        Route::put('trips/{trip}/cancel', [TripController::class, 'cancel']);
        Route::apiResource('trips', TripController::class)->except('destroy');
        Route::get('fuel-logs/support-data', [FuelLogController::class, 'supportData']);
        Route::apiResource('fuel-logs', FuelLogController::class);
        Route::get('odometer-readings/support-data', [OdometerReadingController::class, 'supportData']);
        Route::get('odometer-readings/anomalies', [OdometerReadingController::class, 'anomalies']);
        Route::post('odometer-readings', [OdometerReadingController::class, 'store']);
        Route::put('odometer-readings/{odometerReading}/resolve-anomaly', [OdometerReadingController::class, 'resolveAnomaly']);
        Route::get('vehicles/{vehicle}/odometer-readings', [OdometerReadingController::class, 'byVehicle']);

        // Permissions (read-only)
        Route::get('permissions', [PermissionController::class, 'index']);

        // Settings
        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'bulkUpdate']);

        // Audit Logs (read-only)
        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->middleware('permission:audit-logs.view');
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])
            ->middleware('permission:audit-logs.view');
    });
});
