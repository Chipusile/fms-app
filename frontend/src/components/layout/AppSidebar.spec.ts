import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import { useAuthStore } from '@/stores/auth'
import { makeUser } from '@/test/factories'

const routeState = vi.hoisted(() => ({
  path: '/',
}))

vi.mock('vue-router', () => ({
  useRoute: () => routeState,
}))

describe('AppSidebar', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    routeState.path = '/users'
  })

  it('shows only authorized tenant navigation items', () => {
    const store = useAuthStore()
    store.user = makeUser({
      permissions: [
        'users.view',
        'vehicles.view',
        'settings.view',
        'trips.view',
        'fuel.view',
        'maintenance.view',
        'compliance.view',
        'odometer.view',
        'inspections.view',
        'notifications.view',
        'reports.view',
      ],
    })

    const wrapper = mount(AppSidebar, {
      props: { open: true },
      global: {
        stubs: {
          RouterLink: {
            props: ['to'],
            template: '<a :href="typeof to === \'string\' ? to : to?.path"><slot /></a>',
          },
        },
      },
    })

    expect(wrapper.text()).toContain('Dashboard')
    expect(wrapper.text()).toContain('Vehicles')
    expect(wrapper.text()).toContain('Assignments')
    expect(wrapper.text()).toContain('Onboarding')
    expect(wrapper.text()).toContain('Trips')
    expect(wrapper.text()).toContain('Fuel Logs')
    expect(wrapper.text()).toContain('Maintenance Schedules')
    expect(wrapper.text()).toContain('Maintenance Requests')
    expect(wrapper.text()).toContain('Work Orders')
    expect(wrapper.text()).toContain('Components')
    expect(wrapper.text()).toContain('Compliance')
    expect(wrapper.text()).toContain('Odometer')
    expect(wrapper.text()).toContain('Inspections')
    expect(wrapper.text()).toContain('Notifications')
    expect(wrapper.text()).toContain('Reports')
    expect(wrapper.text()).toContain('Users')
    expect(wrapper.text()).toContain('Settings')
    expect(wrapper.text()).not.toContain('Roles')
    expect(wrapper.text()).not.toContain('Tenants')
    expect(wrapper.text()).not.toContain('Documents')
    expect(wrapper.text()).not.toContain('Inspection Templates')
    expect(wrapper.text()).not.toContain('Incidents')
    expect(wrapper.text()).not.toContain('Approvals')
    expect(wrapper.text()).not.toContain('Audit Logs')
  })

  it('shows maintenance and compliance links only for matching operations permissions', () => {
    const store = useAuthStore()
    store.user = makeUser({
      permissions: ['maintenance.view', 'compliance.view'],
    })

    const wrapper = mount(AppSidebar, {
      props: { open: true },
      global: {
        stubs: {
          RouterLink: {
            props: ['to'],
            template: '<a :href="typeof to === \'string\' ? to : to?.path"><slot /></a>',
          },
        },
      },
    })

    expect(wrapper.text()).toContain('Maintenance Schedules')
    expect(wrapper.text()).toContain('Maintenance Requests')
    expect(wrapper.text()).toContain('Work Orders')
    expect(wrapper.text()).toContain('Components')
    expect(wrapper.text()).toContain('Compliance')
    expect(wrapper.text()).not.toContain('Fuel Logs')
    expect(wrapper.text()).not.toContain('Trips')
    expect(wrapper.text()).not.toContain('Users')
  })

  it('shows fleet master data links only when the user has the matching permissions', () => {
    const store = useAuthStore()
    store.user = makeUser({
      permissions: ['vehicle-types.view', 'drivers.view', 'vendors.view'],
    })

    const wrapper = mount(AppSidebar, {
      props: { open: true },
      global: {
        stubs: {
          RouterLink: {
            props: ['to'],
            template: '<a :href="typeof to === \'string\' ? to : to?.path"><slot /></a>',
          },
        },
      },
    })

    expect(wrapper.text()).toContain('Vehicle Types')
    expect(wrapper.text()).toContain('Drivers')
    expect(wrapper.text()).toContain('Service Providers')
    expect(wrapper.text()).toContain('Onboarding')
    expect(wrapper.text()).not.toContain('Inspection Templates')
    expect(wrapper.text()).not.toContain('Approvals')
    expect(wrapper.text()).not.toContain('Vehicles')
    expect(wrapper.text()).not.toContain('Departments')
  })

  it('shows governance workflow links only when the matching permissions exist', () => {
    const store = useAuthStore()
    store.user = makeUser({
      permissions: ['inspection-templates.view', 'incidents.view', 'approvals.view', 'notifications.view'],
    })

    const wrapper = mount(AppSidebar, {
      props: { open: true },
      global: {
        stubs: {
          RouterLink: {
            props: ['to'],
            template: '<a :href="typeof to === \'string\' ? to : to?.path"><slot /></a>',
          },
        },
      },
    })

    expect(wrapper.text()).toContain('Inspection Templates')
    expect(wrapper.text()).toContain('Incidents')
    expect(wrapper.text()).toContain('Approvals')
    expect(wrapper.text()).toContain('Notifications')
    expect(wrapper.text()).not.toContain('Inspections')
    expect(wrapper.text()).not.toContain('Users')
  })

  it('includes super-admin-only navigation items for platform operators', () => {
    const store = useAuthStore()
    store.user = makeUser({
      tenant_id: null,
      is_super_admin: true,
      permissions: [],
      tenant: undefined,
    })

    const wrapper = mount(AppSidebar, {
      props: { open: true },
      global: {
        stubs: {
          RouterLink: {
            props: ['to'],
            template: '<a :href="typeof to === \'string\' ? to : to?.path"><slot /></a>',
          },
        },
      },
    })

    expect(wrapper.text()).toContain('Tenants')
    expect(wrapper.text()).toContain('Audit Logs')
  })
})
