<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  close: []
}>()

const route = useRoute()
const auth = useAuthStore()

interface NavItem {
  label: string
  to: string
  icon: string
  permission?: string
  permissionsAny?: string[]
  superAdmin?: boolean
}

const icons: Record<string, string> = {
  grid: 'M3 3h7v7H3V3zm11 0h7v7h-7V3zM3 14h7v7H3v-7zm11 0h7v7h-7v-7z',
  truck: 'M10 17h4V5H2v12h3m9 0h2m0 0a2 2 0 1 0 4 0m-4 0a2 2 0 1 0-4 0M5 17a2 2 0 1 0 4 0m-4 0a2 2 0 1 0-4 0m14 0h1a1 1 0 0 0 1-1v-3l-3-4h-4',
  route: 'M9 6l-6 6 6 6M15 6h3a3 3 0 0 1 0 6H9a3 3 0 0 0 0 6h6',
  layers: 'M12 2 3 7l9 5 9-5-9-5zm-9 9 9 5 9-5M3 15l9 5 9-5',
  'id-card': 'M3 5h18v14H3V5zm4 4h5m-5 4h3m6-4a2 2 0 1 1 0 4 2 2 0 0 1 0-4zm-1.5 7a3.5 3.5 0 0 1 5 0',
  'building-2': 'M4 21h16M6 21V7l6-4 6 4v14M9 10h.01M9 14h.01M9 18h.01M15 10h.01M15 14h.01M15 18h.01',
  briefcase: 'M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 9h18v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9zm0 0 7 4h4l7-4',
  folder: 'M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z',
  upload: 'M12 16V4M7 9l5-5 5 5M5 20h14',
  map: 'M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3-6-3zM9 3v15M15 6v15',
  droplets: 'M12 2.69l5.66 5.66a8 8 0 1 1-11.32 0L12 2.69zm0 17.31a3 3 0 0 0 3-3c0-1.35-.74-2.56-2-3.66-1.26 1.1-2 2.31-2 3.66a3 3 0 0 0 3 3z',
  'calendar-clock': 'M8 2v3M16 2v3M3 9h18M5 5h14a2 2 0 0 1 2 2v4.5M5 5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6M17 14a3 3 0 1 0 0 6 3 3 0 0 0 0-6zm0 1.5v2l1.5 1',
  'clipboard-pen': 'M9 3h6a2 2 0 0 1 2 2v1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h1V5a2 2 0 0 1 2-2zm0 3h6M9 12h4m5.5 1.5-4.9 4.9L11 19l.6-2.6 4.9-4.9a1.4 1.4 0 0 1 2 2z',
  wrench: 'M14.7 6.3a4 4 0 0 1-5.4 5.4L4 17l3 3 5.3-5.3a4 4 0 0 0 5.4-5.4l-2.3 2.3-3.4-3.3 2.7-2.3z',
  cpu: 'M9 3v2M15 3v2M9 19v2M15 19v2M3 9h2M3 15h2M19 9h2M19 15h2M7 7h10v10H7V7zm3 3h4v4h-4v-4z',
  'shield-check': 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zm-1-9 2 2 4-4',
  gauge: 'M12 14l4-4M20 12a8 8 0 1 0-16 0m2 0h12M8 18h8',
  'clipboard-list': 'M9 3h6a2 2 0 0 1 2 2v1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h1V5a2 2 0 0 1 2-2zm0 3h6M8 11h8M8 15h8M8 19h5',
  'clipboard-check': 'M9 3h6a2 2 0 0 1 2 2v1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h1V5a2 2 0 0 1 2-2zm0 3h6M9 14l2 2 4-4',
  'alert-triangle': 'M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01',
  'badge-check': 'M12 22l4-2 4 2v-6.5a7 7 0 1 0-16 0V22l4-2 4 2zm-2.5-9 1.8 1.8L15 11',
  bell: 'M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 0 1-6 0m6 0H9',
  'chart-column': 'M4 20V10m6 10V4m6 16v-7m4 7H2',
  users: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm14 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
  shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
  building: 'M3 21h18M3 7v14m6-14v14m6-14v14m6-14v14M3 7l9-4 9 4',
  settings: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z',
  'file-text': 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M16 13H8 M16 17H8 M10 9H8',
}

const navItems = computed<NavItem[]>(() => {
  const items: NavItem[] = [
    { label: 'Dashboard', to: '/', icon: 'grid' },
    { label: 'Vehicles', to: '/vehicles', icon: 'truck', permission: 'vehicles.view' },
    { label: 'Assignments', to: '/vehicle-assignments', icon: 'route', permission: 'vehicles.view' },
    { label: 'Vehicle Types', to: '/vehicle-types', icon: 'layers', permission: 'vehicle-types.view' },
    { label: 'Drivers', to: '/drivers', icon: 'id-card', permission: 'drivers.view' },
    { label: 'Departments', to: '/departments', icon: 'building-2', permission: 'departments.view' },
    { label: 'Service Providers', to: '/service-providers', icon: 'briefcase', permission: 'vendors.view' },
    { label: 'Documents', to: '/asset-documents', icon: 'folder', permission: 'documents.view' },
    { label: 'Onboarding', to: '/onboarding-templates', icon: 'upload', permissionsAny: ['vehicles.view', 'drivers.view', 'vehicles.create', 'drivers.create'] },
    { label: 'Trips', to: '/trips', icon: 'map', permission: 'trips.view' },
    { label: 'Fuel Logs', to: '/fuel-logs', icon: 'droplets', permission: 'fuel.view' },
    { label: 'Maintenance Schedules', to: '/maintenance-schedules', icon: 'calendar-clock', permission: 'maintenance.view' },
    { label: 'Maintenance Requests', to: '/maintenance-requests', icon: 'clipboard-pen', permission: 'maintenance.view' },
    { label: 'Work Orders', to: '/work-orders', icon: 'wrench', permission: 'maintenance.view' },
    { label: 'Components', to: '/vehicle-components', icon: 'cpu', permission: 'maintenance.view' },
    { label: 'Compliance', to: '/compliance', icon: 'shield-check', permission: 'compliance.view' },
    { label: 'Odometer', to: '/odometer', icon: 'gauge', permission: 'odometer.view' },
    { label: 'Inspection Templates', to: '/inspection-templates', icon: 'clipboard-list', permission: 'inspection-templates.view' },
    { label: 'Inspections', to: '/inspections', icon: 'clipboard-check', permission: 'inspections.view' },
    { label: 'Incidents', to: '/incidents', icon: 'alert-triangle', permission: 'incidents.view' },
    { label: 'Approvals', to: '/approvals', icon: 'badge-check', permission: 'approvals.view' },
    { label: 'Notifications', to: '/notifications', icon: 'bell', permission: 'notifications.view' },
    { label: 'Reports', to: '/reports', icon: 'chart-column', permission: 'reports.view' },
    { label: 'Users', to: '/users', icon: 'users', permission: 'users.view' },
    { label: 'Roles', to: '/roles', icon: 'shield', permission: 'roles.view' },
    { label: 'Tenants', to: '/tenants', icon: 'building', superAdmin: true },
    { label: 'Settings', to: '/settings', icon: 'settings', permission: 'settings.view' },
    { label: 'Audit Logs', to: '/audit-logs', icon: 'file-text', permission: 'audit-logs.view' },
  ]

  return items.filter((item) => {
    if (item.superAdmin) return auth.isSuperAdmin
    if (item.permission) return auth.hasPermission(item.permission)
    if (item.permissionsAny) return auth.hasAnyPermission(item.permissionsAny)
    return true
  })
})

function isActive(path: string): boolean {
  if (path === '/') return route.path === '/'
  return route.path.startsWith(path)
}
</script>

<template>
  <aside
    :class="[
      'fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-white dark:bg-slate-900 border-r border-gray-200 dark:border-slate-800 transition-transform lg:translate-x-0',
      open ? 'translate-x-0' : '-translate-x-full',
    ]"
  >
    <!-- Logo -->
    <div class="flex h-16 items-center gap-3 border-b border-gray-200 dark:border-slate-800 px-6">
      <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-600 text-white text-sm font-bold">
        F
      </div>
      <span class="text-lg font-semibold text-gray-900 dark:text-slate-100">Fleet MS</span>
    </div>

    <!-- Navigation -->
    <nav
      aria-label="Primary"
      class="flex-1 overflow-y-auto px-3 py-4"
    >
      <ul class="space-y-1">
        <li v-for="item in navItems" :key="item.to">
          <RouterLink
            :to="item.to"
            :class="[
              'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
              isActive(item.to)
                ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/40 dark:text-primary-200'
                : 'text-gray-700 hover:bg-gray-100 dark:hover:bg-slate-800 dark:text-slate-300 dark:hover:text-slate-100',
            ]"
            @click="emit('close')"
          >
            <svg
              class="h-5 w-5 shrink-0"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path :d="icons[item.icon] ?? ''" />
            </svg>
            {{ item.label }}
          </RouterLink>
        </li>
      </ul>
    </nav>

    <!-- Tenant info -->
    <div
      v-if="auth.user?.tenant"
      class="border-t border-gray-200 dark:border-slate-800 px-4 py-3"
    >
      <p class="text-xs text-gray-500 dark:text-slate-400">Organisation</p>
      <p class="text-sm font-medium text-gray-900 dark:text-slate-100 truncate">
        {{ auth.user.tenant.name }}
      </p>
    </div>
  </aside>
</template>
