import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api, { initCsrf } from '@/plugins/axios'
import type { User, ApiResponse } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)
  const initialized = ref(false)
  let fetchUserRequest: Promise<void> | null = null

  const isAuthenticated = computed(() => !!user.value)
  const isSuperAdmin = computed(() => user.value?.is_super_admin === true)
  const tenantId = computed(() => user.value?.tenant_id)
  const permissions = computed(() => user.value?.permissions ?? [])

  function hasPermission(permission: string): boolean {
    if (isSuperAdmin.value) return true
    return permissions.value.includes(permission)
  }

  function hasAnyPermission(perms: string[]): boolean {
    if (isSuperAdmin.value) return true
    return perms.some((p) => permissions.value.includes(p))
  }

  async function login(email: string, password: string): Promise<void> {
    await initCsrf()
    const response = await api.post<ApiResponse<User>>('/auth/login', { email, password })
    user.value = response.data.data
    initialized.value = true
  }

  async function logout(): Promise<void> {
    await api.post('/auth/logout')
    user.value = null
    initialized.value = true
  }

  async function fetchUser(): Promise<void> {
    if (fetchUserRequest) {
      await fetchUserRequest
      return
    }

    fetchUserRequest = (async () => {
      try {
        loading.value = true
        const response = await api.get<ApiResponse<User>>('/auth/me')
        user.value = response.data.data
      } catch {
        user.value = null
      } finally {
        loading.value = false
        initialized.value = true
        fetchUserRequest = null
      }
    })()

    await fetchUserRequest
  }

  return {
    user,
    loading,
    initialized,
    isAuthenticated,
    isSuperAdmin,
    tenantId,
    permissions,
    hasPermission,
    hasAnyPermission,
    login,
    logout,
    fetchUser,
  }
})
