import { setActivePinia, createPinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { useAuthStore } from '@/stores/auth'
import { makeUser } from '@/test/factories'

describe('useAuthStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('evaluates direct and aggregate permissions for tenant users', () => {
    const store = useAuthStore()
    store.user = makeUser({
      permissions: ['users.view', 'users.update'],
    })

    expect(store.hasPermission('users.view')).toBe(true)
    expect(store.hasPermission('roles.view')).toBe(false)
    expect(store.hasAnyPermission(['roles.view', 'users.update'])).toBe(true)
    expect(store.hasAnyPermission(['roles.delete', 'settings.update'])).toBe(false)
  })

  it('treats super admins as authorized for all permission checks', () => {
    const store = useAuthStore()
    store.user = makeUser({
      tenant_id: null,
      is_super_admin: true,
      permissions: [],
    })

    expect(store.hasPermission('tenants.delete')).toBe(true)
    expect(store.hasAnyPermission(['audit-logs.view', 'settings.update'])).toBe(true)
  })
})
