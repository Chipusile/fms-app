<?php

namespace Database\Seeders;

use App\Enums\TenantStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seeds sample tenants with their default roles.
 * Each tenant gets the full set of system roles with appropriate permissions.
 */
class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            [
                'name' => 'Acme Transport',
                'slug' => 'acme-transport',
                'email' => 'admin@acme-transport.com',
                'status' => TenantStatus::Active,
                'timezone' => 'Africa/Lusaka',
                'currency' => 'ZMW',
                'country' => 'Zambia',
                'city' => 'Lusaka',
            ],
            [
                'name' => 'Global Logistics Ltd',
                'slug' => 'global-logistics',
                'email' => 'admin@global-logistics.com',
                'status' => TenantStatus::Active,
                'timezone' => 'Africa/Johannesburg',
                'currency' => 'ZAR',
                'country' => 'South Africa',
                'city' => 'Johannesburg',
            ],
        ];

        foreach ($tenants as $tenantData) {
            $tenant = Tenant::updateOrCreate(
                ['slug' => $tenantData['slug']],
                $tenantData
            );

            $this->createDefaultRoles($tenant);
        }
    }

    private function createDefaultRoles(Tenant $tenant): void
    {
        $allPermissions = Permission::pluck('id', 'slug');

        $roles = $this->getDefaultRoleDefinitions();

        foreach ($roles as $roleData) {
            $role = Role::updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $roleData['slug']],
                [
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                    'is_system' => true,
                ]
            );

            // Map permission slugs to IDs
            $permissionIds = collect($roleData['permissions'])
                ->map(fn ($slug) => $allPermissions->get($slug))
                ->filter()
                ->values()
                ->all();

            $role->permissions()->sync($permissionIds);
        }
    }

    private function getDefaultRoleDefinitions(): array
    {
        return [
            [
                'name' => 'Tenant Admin',
                'slug' => 'tenant-admin',
                'description' => 'Full access to all tenant features',
                'permissions' => Permission::pluck('slug')->all(),
            ],
            [
                'name' => 'Fleet Manager',
                'slug' => 'fleet-manager',
                'description' => 'Manages vehicles, drivers, assignments, and fleet operations',
                'permissions' => [
                    'vehicle-types.view', 'vehicle-types.create', 'vehicle-types.update', 'vehicle-types.delete',
                    'vehicles.view', 'vehicles.create', 'vehicles.update', 'vehicles.delete', 'vehicles.assign',
                    'drivers.view', 'drivers.create', 'drivers.update', 'drivers.delete',
                    'inspection-templates.view', 'inspection-templates.create', 'inspection-templates.update', 'inspection-templates.delete',
                    'departments.view', 'departments.create', 'departments.update', 'departments.delete',
                    'documents.view',
                    'trips.view', 'trips.create', 'trips.update', 'trips.approve',
                    'fuel.view', 'fuel.create', 'fuel.update',
                    'odometer.view', 'odometer.create', 'odometer.update',
                    'maintenance.view', 'maintenance.create', 'maintenance.update', 'maintenance.delete',
                    'inspections.view', 'inspections.create', 'inspections.update',
                    'incidents.view', 'incidents.create', 'incidents.update',
                    'approvals.view', 'approvals.decide',
                    'notifications.view', 'notifications.update',
                    'vendors.view',
                    'reports.view', 'reports.export',
                ],
            ],
            [
                'name' => 'Transport Officer',
                'slug' => 'transport-officer',
                'description' => 'Manages daily trip scheduling and driver assignments',
                'permissions' => [
                    'vehicle-types.view',
                    'vehicles.view', 'vehicles.assign',
                    'drivers.view',
                    'inspection-templates.view',
                    'trips.view', 'trips.create', 'trips.update',
                    'fuel.view', 'fuel.create',
                    'odometer.view', 'odometer.create',
                    'inspections.view', 'inspections.create',
                    'incidents.view', 'incidents.create',
                    'notifications.view', 'notifications.update',
                ],
            ],
            [
                'name' => 'Maintenance Officer',
                'slug' => 'maintenance-officer',
                'description' => 'Manages vehicle maintenance, work orders, and service providers',
                'permissions' => [
                    'vehicle-types.view',
                    'vehicles.view',
                    'inspection-templates.view',
                    'odometer.view', 'odometer.create', 'odometer.update',
                    'maintenance.view', 'maintenance.create', 'maintenance.update', 'maintenance.delete', 'maintenance.approve',
                    'inspections.view', 'inspections.create', 'inspections.update',
                    'incidents.view',
                    'approvals.view',
                    'notifications.view', 'notifications.update',
                    'vendors.view', 'vendors.create', 'vendors.update',
                    'reports.view',
                ],
            ],
            [
                'name' => 'Compliance Officer',
                'slug' => 'compliance-officer',
                'description' => 'Manages compliance, documents, and renewals',
                'permissions' => [
                    'vehicle-types.view',
                    'vehicles.view',
                    'drivers.view',
                    'vendors.view',
                    'inspections.view', 'inspections.update',
                    'incidents.view', 'incidents.update',
                    'approvals.view', 'approvals.decide',
                    'notifications.view', 'notifications.update',
                    'documents.view', 'documents.create', 'documents.update',
                    'compliance.view', 'compliance.create', 'compliance.update', 'compliance.delete',
                    'reports.view', 'reports.export',
                    'audit-logs.view',
                ],
            ],
            [
                'name' => 'Finance Officer',
                'slug' => 'finance-officer',
                'description' => 'Views cost-related data and reports',
                'permissions' => [
                    'vehicles.view',
                    'fuel.view',
                    'odometer.view',
                    'incidents.view',
                    'notifications.view', 'notifications.update',
                    'maintenance.view',
                    'reports.view', 'reports.export',
                ],
            ],
            [
                'name' => 'Driver',
                'slug' => 'driver',
                'description' => 'Views own assignments, logs trips and fuel',
                'permissions' => [
                    'vehicles.view',
                    'trips.view', 'trips.create',
                    'fuel.view', 'fuel.create',
                    'odometer.view',
                    'inspections.view', 'inspections.create',
                    'incidents.view', 'incidents.create',
                    'notifications.view', 'notifications.update',
                ],
            ],
            [
                'name' => 'Department Manager',
                'slug' => 'department-manager',
                'description' => 'Manages department vehicle requests',
                'permissions' => [
                    'vehicles.view',
                    'departments.view',
                    'trips.view', 'trips.create', 'trips.approve',
                    'inspections.view',
                    'incidents.view',
                    'approvals.view', 'approvals.decide',
                    'notifications.view', 'notifications.update',
                    'reports.view',
                ],
            ],
            [
                'name' => 'Viewer',
                'slug' => 'viewer',
                'description' => 'Read-only access to dashboards and reports',
                'permissions' => [
                    'vehicle-types.view',
                    'vehicles.view',
                    'drivers.view',
                    'departments.view',
                    'inspection-templates.view',
                    'vendors.view',
                    'trips.view',
                    'fuel.view',
                    'odometer.view',
                    'inspections.view',
                    'incidents.view',
                    'notifications.view', 'notifications.update',
                    'maintenance.view',
                    'reports.view',
                ],
            ],
        ];
    }
}
