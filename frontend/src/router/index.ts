import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/modules/auth/LoginPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/modules/auth/RegisterPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/modules/auth/ForgotPasswordPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/modules/auth/ResetPasswordPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/accept-invite',
      name: 'accept-invite',
      component: () => import('@/modules/auth/AcceptInvitePage.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      component: () => import('@/components/layout/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/modules/dashboard/DashboardPage.vue'),
        },
        {
          path: 'vehicles',
          name: 'vehicles',
          component: () => import('@/modules/vehicles/VehicleListPage.vue'),
          meta: { permission: 'vehicles.view' },
        },
        {
          path: 'vehicles/create',
          name: 'vehicles.create',
          component: () => import('@/modules/vehicles/VehicleFormPage.vue'),
          meta: { permission: 'vehicles.create' },
        },
        {
          path: 'vehicles/:id/edit',
          name: 'vehicles.edit',
          component: () => import('@/modules/vehicles/VehicleFormPage.vue'),
          meta: { permission: 'vehicles.update' },
        },
        {
          path: 'vehicle-assignments',
          name: 'vehicle-assignments',
          component: () => import('@/modules/vehicle-assignments/VehicleAssignmentListPage.vue'),
          meta: { permission: 'vehicles.view' },
        },
        {
          path: 'vehicle-assignments/create',
          name: 'vehicle-assignments.create',
          component: () => import('@/modules/vehicle-assignments/VehicleAssignmentFormPage.vue'),
          meta: { permission: 'vehicles.assign' },
        },
        {
          path: 'vehicle-assignments/:id/edit',
          name: 'vehicle-assignments.edit',
          component: () => import('@/modules/vehicle-assignments/VehicleAssignmentFormPage.vue'),
          meta: { permission: 'vehicles.assign' },
        },
        {
          path: 'vehicle-types',
          name: 'vehicle-types',
          component: () => import('@/modules/vehicle-types/VehicleTypeListPage.vue'),
          meta: { permission: 'vehicle-types.view' },
        },
        {
          path: 'vehicle-types/create',
          name: 'vehicle-types.create',
          component: () => import('@/modules/vehicle-types/VehicleTypeFormPage.vue'),
          meta: { permission: 'vehicle-types.create' },
        },
        {
          path: 'vehicle-types/:id/edit',
          name: 'vehicle-types.edit',
          component: () => import('@/modules/vehicle-types/VehicleTypeFormPage.vue'),
          meta: { permission: 'vehicle-types.update' },
        },
        {
          path: 'drivers',
          name: 'drivers',
          component: () => import('@/modules/drivers/DriverListPage.vue'),
          meta: { permission: 'drivers.view' },
        },
        {
          path: 'drivers/create',
          name: 'drivers.create',
          component: () => import('@/modules/drivers/DriverFormPage.vue'),
          meta: { permission: 'drivers.create' },
        },
        {
          path: 'drivers/:id/edit',
          name: 'drivers.edit',
          component: () => import('@/modules/drivers/DriverFormPage.vue'),
          meta: { permission: 'drivers.update' },
        },
        {
          path: 'departments',
          name: 'departments',
          component: () => import('@/modules/departments/DepartmentListPage.vue'),
          meta: { permission: 'departments.view' },
        },
        {
          path: 'departments/create',
          name: 'departments.create',
          component: () => import('@/modules/departments/DepartmentFormPage.vue'),
          meta: { permission: 'departments.create' },
        },
        {
          path: 'departments/:id/edit',
          name: 'departments.edit',
          component: () => import('@/modules/departments/DepartmentFormPage.vue'),
          meta: { permission: 'departments.update' },
        },
        {
          path: 'service-providers',
          name: 'service-providers',
          component: () => import('@/modules/service-providers/ServiceProviderListPage.vue'),
          meta: { permission: 'vendors.view' },
        },
        {
          path: 'service-providers/create',
          name: 'service-providers.create',
          component: () => import('@/modules/service-providers/ServiceProviderFormPage.vue'),
          meta: { permission: 'vendors.create' },
        },
        {
          path: 'service-providers/:id/edit',
          name: 'service-providers.edit',
          component: () => import('@/modules/service-providers/ServiceProviderFormPage.vue'),
          meta: { permission: 'vendors.update' },
        },
        {
          path: 'asset-documents',
          name: 'asset-documents',
          component: () => import('@/modules/documents/AssetDocumentListPage.vue'),
          meta: { permission: 'documents.view' },
        },
        {
          path: 'asset-documents/create',
          name: 'asset-documents.create',
          component: () => import('@/modules/documents/AssetDocumentFormPage.vue'),
          meta: { permission: 'documents.create' },
        },
        {
          path: 'asset-documents/:id/edit',
          name: 'asset-documents.edit',
          component: () => import('@/modules/documents/AssetDocumentFormPage.vue'),
          meta: { permission: 'documents.update' },
        },
        {
          path: 'onboarding-templates',
          name: 'onboarding-templates',
          component: () => import('@/modules/import-templates/ImportTemplatesPage.vue'),
          meta: { permissionsAny: ['vehicles.view', 'drivers.view', 'vehicles.create', 'drivers.create'] },
        },
        {
          path: 'trips',
          name: 'trips',
          component: () => import('@/modules/trips/TripListPage.vue'),
          meta: { permission: 'trips.view' },
        },
        {
          path: 'trips/create',
          name: 'trips.create',
          component: () => import('@/modules/trips/TripFormPage.vue'),
          meta: { permission: 'trips.create' },
        },
        {
          path: 'trips/:id/edit',
          name: 'trips.edit',
          component: () => import('@/modules/trips/TripFormPage.vue'),
          meta: { permission: 'trips.view' },
        },
        {
          path: 'fuel-logs',
          name: 'fuel-logs',
          component: () => import('@/modules/fuel-logs/FuelLogListPage.vue'),
          meta: { permission: 'fuel.view' },
        },
        {
          path: 'fuel-logs/create',
          name: 'fuel-logs.create',
          component: () => import('@/modules/fuel-logs/FuelLogFormPage.vue'),
          meta: { permission: 'fuel.create' },
        },
        {
          path: 'fuel-logs/:id/edit',
          name: 'fuel-logs.edit',
          component: () => import('@/modules/fuel-logs/FuelLogFormPage.vue'),
          meta: { permissionsAny: ['fuel.update', 'fuel.view'] },
        },
        {
          path: 'maintenance-schedules',
          name: 'maintenance-schedules',
          component: () => import('@/modules/maintenance-schedules/MaintenanceScheduleListPage.vue'),
          meta: { permission: 'maintenance.view' },
        },
        {
          path: 'maintenance-schedules/create',
          name: 'maintenance-schedules.create',
          component: () => import('@/modules/maintenance-schedules/MaintenanceScheduleFormPage.vue'),
          meta: { permission: 'maintenance.create' },
        },
        {
          path: 'maintenance-schedules/:id/edit',
          name: 'maintenance-schedules.edit',
          component: () => import('@/modules/maintenance-schedules/MaintenanceScheduleFormPage.vue'),
          meta: { permission: 'maintenance.update' },
        },
        {
          path: 'maintenance-requests',
          name: 'maintenance-requests',
          component: () => import('@/modules/maintenance-requests/MaintenanceRequestListPage.vue'),
          meta: { permission: 'maintenance.view' },
        },
        {
          path: 'maintenance-requests/create',
          name: 'maintenance-requests.create',
          component: () => import('@/modules/maintenance-requests/MaintenanceRequestFormPage.vue'),
          meta: { permission: 'maintenance.create' },
        },
        {
          path: 'maintenance-requests/:id/edit',
          name: 'maintenance-requests.edit',
          component: () => import('@/modules/maintenance-requests/MaintenanceRequestFormPage.vue'),
          meta: { permission: 'maintenance.view' },
        },
        {
          path: 'work-orders',
          name: 'work-orders',
          component: () => import('@/modules/work-orders/WorkOrderListPage.vue'),
          meta: { permission: 'maintenance.view' },
        },
        {
          path: 'work-orders/create',
          name: 'work-orders.create',
          component: () => import('@/modules/work-orders/WorkOrderFormPage.vue'),
          meta: { permission: 'maintenance.create' },
        },
        {
          path: 'work-orders/:id/edit',
          name: 'work-orders.edit',
          component: () => import('@/modules/work-orders/WorkOrderFormPage.vue'),
          meta: { permission: 'maintenance.view' },
        },
        {
          path: 'vehicle-components',
          name: 'vehicle-components',
          component: () => import('@/modules/vehicle-components/VehicleComponentListPage.vue'),
          meta: { permission: 'maintenance.view' },
        },
        {
          path: 'vehicle-components/create',
          name: 'vehicle-components.create',
          component: () => import('@/modules/vehicle-components/VehicleComponentFormPage.vue'),
          meta: { permission: 'maintenance.create' },
        },
        {
          path: 'vehicle-components/:id/edit',
          name: 'vehicle-components.edit',
          component: () => import('@/modules/vehicle-components/VehicleComponentFormPage.vue'),
          meta: { permission: 'maintenance.view' },
        },
        {
          path: 'compliance',
          name: 'compliance',
          component: () => import('@/modules/compliance/ComplianceItemListPage.vue'),
          meta: { permission: 'compliance.view' },
        },
        {
          path: 'compliance/create',
          name: 'compliance.create',
          component: () => import('@/modules/compliance/ComplianceItemFormPage.vue'),
          meta: { permission: 'compliance.create' },
        },
        {
          path: 'compliance/:id/edit',
          name: 'compliance.edit',
          component: () => import('@/modules/compliance/ComplianceItemFormPage.vue'),
          meta: { permission: 'compliance.view' },
        },
        {
          path: 'odometer',
          name: 'odometer',
          component: () => import('@/modules/odometer/OdometerAnomalyPage.vue'),
          meta: { permission: 'odometer.view' },
        },
        {
          path: 'inspection-templates',
          name: 'inspection-templates',
          component: () => import('@/modules/inspection-templates/InspectionTemplateListPage.vue'),
          meta: { permission: 'inspection-templates.view' },
        },
        {
          path: 'inspection-templates/create',
          name: 'inspection-templates.create',
          component: () => import('@/modules/inspection-templates/InspectionTemplateFormPage.vue'),
          meta: { permission: 'inspection-templates.create' },
        },
        {
          path: 'inspection-templates/:id/edit',
          name: 'inspection-templates.edit',
          component: () => import('@/modules/inspection-templates/InspectionTemplateFormPage.vue'),
          meta: { permission: 'inspection-templates.update' },
        },
        {
          path: 'inspections',
          name: 'inspections',
          component: () => import('@/modules/inspections/InspectionListPage.vue'),
          meta: { permission: 'inspections.view' },
        },
        {
          path: 'inspections/create',
          name: 'inspections.create',
          component: () => import('@/modules/inspections/InspectionFormPage.vue'),
          meta: { permission: 'inspections.create' },
        },
        {
          path: 'inspections/:id',
          name: 'inspections.show',
          component: () => import('@/modules/inspections/InspectionFormPage.vue'),
          meta: { permission: 'inspections.view' },
        },
        {
          path: 'incidents',
          name: 'incidents',
          component: () => import('@/modules/incidents/IncidentListPage.vue'),
          meta: { permission: 'incidents.view' },
        },
        {
          path: 'incidents/create',
          name: 'incidents.create',
          component: () => import('@/modules/incidents/IncidentFormPage.vue'),
          meta: { permission: 'incidents.create' },
        },
        {
          path: 'incidents/:id/edit',
          name: 'incidents.edit',
          component: () => import('@/modules/incidents/IncidentFormPage.vue'),
          meta: { permission: 'incidents.view' },
        },
        {
          path: 'approvals',
          name: 'approvals',
          component: () => import('@/modules/approvals/ApprovalQueuePage.vue'),
          meta: { permission: 'approvals.view' },
        },
        {
          path: 'notifications',
          name: 'notifications',
          component: () => import('@/modules/notifications/NotificationInboxPage.vue'),
          meta: { permission: 'notifications.view' },
        },
        {
          path: 'reports',
          name: 'reports',
          component: () => import('@/modules/reports/ReportCenterPage.vue'),
          meta: { permission: 'reports.view' },
        },
        {
          path: 'users',
          name: 'users',
          component: () => import('@/modules/users/UserListPage.vue'),
          meta: { permission: 'users.view' },
        },
        {
          path: 'users/create',
          name: 'users.create',
          component: () => import('@/modules/users/UserFormPage.vue'),
          meta: { permission: 'users.create' },
        },
        {
          path: 'users/:id/edit',
          name: 'users.edit',
          component: () => import('@/modules/users/UserFormPage.vue'),
          meta: { permission: 'users.update' },
        },
        {
          path: 'roles',
          name: 'roles',
          component: () => import('@/modules/roles/RoleListPage.vue'),
          meta: { permission: 'roles.view' },
        },
        {
          path: 'roles/create',
          name: 'roles.create',
          component: () => import('@/modules/roles/RoleFormPage.vue'),
          meta: { permission: 'roles.create' },
        },
        {
          path: 'roles/:id/edit',
          name: 'roles.edit',
          component: () => import('@/modules/roles/RoleFormPage.vue'),
          meta: { permission: 'roles.update' },
        },
        {
          path: 'tenants',
          name: 'tenants',
          component: () => import('@/modules/tenants/TenantListPage.vue'),
          meta: { superAdmin: true },
        },
        {
          path: 'tenants/create',
          name: 'tenants.create',
          component: () => import('@/modules/tenants/TenantFormPage.vue'),
          meta: { superAdmin: true },
        },
        {
          path: 'tenants/:id/edit',
          name: 'tenants.edit',
          component: () => import('@/modules/tenants/TenantFormPage.vue'),
          meta: { superAdmin: true },
        },
        {
          path: 'settings',
          name: 'settings',
          component: () => import('@/modules/settings/SettingsPage.vue'),
          meta: { permission: 'settings.view' },
        },
        {
          path: 'audit-logs',
          name: 'audit-logs',
          component: () => import('@/modules/audit/AuditLogPage.vue'),
          meta: { permission: 'audit-logs.view' },
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundPage.vue'),
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const requiresResolvedAuth = Boolean(
    to.meta.requiresAuth
    || to.meta.superAdmin
    || to.meta.permission
    || to.meta.permissionsAny
  )

  // Only resolve auth state when the destination actually depends on it.
  if (!auth.initialized && requiresResolvedAuth) {
    await auth.fetchUser()
  }

  // Redirect authenticated users away from guest-only pages
  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  // Redirect unauthenticated users to login
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  // Check super admin requirement
  if (to.meta.superAdmin && !auth.isSuperAdmin) {
    return { name: 'dashboard' }
  }

  // Check permission requirement
  if (to.meta.permission && !auth.hasPermission(to.meta.permission as string)) {
    return { name: 'dashboard' }
  }

  if (to.meta.permissionsAny && !auth.hasAnyPermission(to.meta.permissionsAny as string[])) {
    return { name: 'dashboard' }
  }
})

export default router
