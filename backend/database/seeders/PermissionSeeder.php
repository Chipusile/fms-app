<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Seeds all system permissions. Permissions are global (not tenant-scoped).
 * They define what actions exist in the system. New modules should add
 * their permissions here.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = $this->getPermissions();

        foreach ($permissions as $module => $actions) {
            foreach ($actions as $action => $description) {
                Permission::updateOrCreate(
                    ['slug' => "{$module}.{$action}"],
                    [
                        'name' => ucfirst($action).' '.ucfirst(str_replace('-', ' ', $module)),
                        'module' => $module,
                        'description' => $description,
                    ]
                );
            }
        }
    }

    private function getPermissions(): array
    {
        return [
            'tenants' => [
                'view' => 'View tenant details',
                'create' => 'Create new tenants',
                'update' => 'Update tenant settings',
                'delete' => 'Deactivate tenants',
            ],
            'users' => [
                'view' => 'View user list and details',
                'create' => 'Create new users',
                'update' => 'Update user profiles and roles',
                'delete' => 'Deactivate user accounts',
            ],
            'roles' => [
                'view' => 'View roles and permissions',
                'create' => 'Create new roles',
                'update' => 'Update role permissions',
                'delete' => 'Delete roles',
            ],
            'vehicles' => [
                'view' => 'View vehicle list and details',
                'create' => 'Register new vehicles',
                'update' => 'Update vehicle information',
                'delete' => 'Deactivate vehicles',
                'assign' => 'Assign vehicles to drivers or departments',
            ],
            'vehicle-types' => [
                'view' => 'View vehicle type catalogues',
                'create' => 'Create vehicle types',
                'update' => 'Update vehicle types',
                'delete' => 'Delete vehicle types',
            ],
            'drivers' => [
                'view' => 'View driver list and details',
                'create' => 'Register new drivers',
                'update' => 'Update driver information',
                'delete' => 'Deactivate driver records',
            ],
            'inspection-templates' => [
                'view' => 'View inspection checklist templates',
                'create' => 'Create inspection checklist templates',
                'update' => 'Update inspection checklist templates',
                'delete' => 'Delete inspection checklist templates',
            ],
            'departments' => [
                'view' => 'View departments',
                'create' => 'Create departments',
                'update' => 'Update departments',
                'delete' => 'Delete departments',
            ],
            'trips' => [
                'view' => 'View trip requests and logs',
                'create' => 'Create trip requests',
                'update' => 'Update trip details',
                'delete' => 'Cancel trips',
                'approve' => 'Approve or reject trip requests',
            ],
            'fuel' => [
                'view' => 'View fuel logs',
                'create' => 'Log fuel entries',
                'update' => 'Update fuel entries',
                'delete' => 'Delete fuel entries',
            ],
            'odometer' => [
                'view' => 'View odometer readings and anomalies',
                'create' => 'Create odometer readings',
                'update' => 'Resolve odometer anomalies',
            ],
            'maintenance' => [
                'view' => 'View maintenance schedules and records',
                'create' => 'Create maintenance requests',
                'update' => 'Update maintenance records',
                'delete' => 'Delete maintenance records',
                'approve' => 'Approve maintenance requests',
            ],
            'inspections' => [
                'view' => 'View inspections',
                'create' => 'Perform inspections',
                'update' => 'Update inspection results',
            ],
            'incidents' => [
                'view' => 'View incident reports',
                'create' => 'Report incidents',
                'update' => 'Update incident reports',
                'delete' => 'Delete incident reports',
            ],
            'compliance' => [
                'view' => 'View compliance items',
                'create' => 'Create compliance entries',
                'update' => 'Update compliance entries',
                'delete' => 'Delete compliance entries',
            ],
            'documents' => [
                'view' => 'View asset documents',
                'create' => 'Create asset documents',
                'update' => 'Update asset documents',
                'delete' => 'Delete asset documents',
            ],
            'vendors' => [
                'view' => 'View service providers',
                'create' => 'Add service providers',
                'update' => 'Update service providers',
                'delete' => 'Remove service providers',
            ],
            'reports' => [
                'view' => 'View reports and dashboards',
                'export' => 'Export reports',
            ],
            'settings' => [
                'view' => 'View system settings',
                'update' => 'Update system settings',
            ],
            'audit-logs' => [
                'view' => 'View audit logs',
            ],
            'approvals' => [
                'view' => 'View approval requests',
                'decide' => 'Approve or reject approval requests',
            ],
            'notifications' => [
                'view' => 'View in-app notifications',
                'update' => 'Update notification status',
            ],
        ];
    }
}
