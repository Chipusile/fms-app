<?php

namespace App\Providers;

use App\Models\ApprovalRequest;
use App\Models\AssetDocument;
use App\Models\AuditLog;
use App\Models\ComplianceItem;
use App\Models\Department;
use App\Models\Driver;
use App\Models\FuelLog;
use App\Models\Incident;
use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\OdometerReading;
use App\Models\Permission;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\ServiceProvider as ServiceProviderModel;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleComponent;
use App\Models\VehicleType;
use App\Models\WorkOrder;
use App\Policies\ApprovalRequestPolicy;
use App\Policies\AssetDocumentPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\ComplianceItemPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\DriverPolicy;
use App\Policies\FuelLogPolicy;
use App\Policies\IncidentPolicy;
use App\Policies\InspectionPolicy;
use App\Policies\InspectionTemplatePolicy;
use App\Policies\MaintenanceRequestPolicy;
use App\Policies\MaintenanceSchedulePolicy;
use App\Policies\OdometerReadingPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\ReportExportPolicy;
use App\Policies\RolePolicy;
use App\Policies\ServiceProviderPolicy;
use App\Policies\SettingPolicy;
use App\Policies\TenantPolicy;
use App\Policies\TripPolicy;
use App\Policies\UserNotificationPolicy;
use App\Policies\UserPolicy;
use App\Policies\VehicleAssignmentPolicy;
use App\Policies\VehicleComponentPolicy;
use App\Policies\VehiclePolicy;
use App\Policies\VehicleTypePolicy;
use App\Policies\WorkOrderPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised());

        RateLimiter::for('auth-login', function (Request $request) {
            $email = (string) $request->input('email', 'guest');

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($email),
            ];
        });

        RateLimiter::for('report-exports', function (Request $request) {
            $user = $request->user();
            $key = $user
                ? 'tenant:'.$user->tenant_id.'|user:'.$user->id
                : 'guest|'.$request->ip();

            return [
                Limit::perMinute(10)->by($key),
            ];
        });

        RateLimiter::for('asset-downloads', function (Request $request) {
            $user = $request->user();
            $key = $user
                ? 'tenant:'.$user->tenant_id.'|user:'.$user->id
                : 'guest|'.$request->ip();

            return [
                Limit::perMinute(60)->by($key),
            ];
        });

        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(AssetDocument::class, AssetDocumentPolicy::class);
        Gate::policy(ApprovalRequest::class, ApprovalRequestPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Driver::class, DriverPolicy::class);
        Gate::policy(FuelLog::class, FuelLogPolicy::class);
        Gate::policy(Incident::class, IncidentPolicy::class);
        Gate::policy(Inspection::class, InspectionPolicy::class);
        Gate::policy(InspectionTemplate::class, InspectionTemplatePolicy::class);
        Gate::policy(ComplianceItem::class, ComplianceItemPolicy::class);
        Gate::policy(MaintenanceSchedule::class, MaintenanceSchedulePolicy::class);
        Gate::policy(MaintenanceRequest::class, MaintenanceRequestPolicy::class);
        Gate::policy(WorkOrder::class, WorkOrderPolicy::class);
        Gate::policy(OdometerReading::class, OdometerReadingPolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(ReportExport::class, ReportExportPolicy::class);
        Gate::policy(ServiceProviderModel::class, ServiceProviderPolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Trip::class, TripPolicy::class);
        Gate::policy(UserNotification::class, UserNotificationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(VehicleComponent::class, VehicleComponentPolicy::class);
        Gate::policy(VehicleAssignment::class, VehicleAssignmentPolicy::class);
        Gate::policy(VehicleType::class, VehicleTypePolicy::class);
    }
}
