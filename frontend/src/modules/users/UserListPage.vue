<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { userStatusOptions } from '@/lib/options'
import { destroyResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type { ApiError, PaginationMeta, Role, User } from '@/types'

const auth = useAuthStore()
const baseColumns = [
  { key: 'name', label: 'User' },
  { key: 'email', label: 'Email' },
  { key: 'roles', label: 'Roles' },
  { key: 'status', label: 'Status' },
]

const users = ref<User[]>([])
const roles = ref<Role[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const roleFilter = ref('')
const meta = ref<PaginationMeta>({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
})
const canCreateUsers = computed(() => auth.hasPermission('users.create'))
const canManageUsers = computed(() => auth.hasAnyPermission(['users.update', 'users.delete']))
const columns = computed(() => (
  canManageUsers.value
    ? [...baseColumns, { key: 'actions', label: 'Actions' }]
    : baseColumns
))

const rows = computed(() => users.value.map((user) => ({
  id: user.id,
  name: user.name,
  email: user.email,
  roles: user.roles?.map((role) => role.name).join(', ') || 'Unassigned',
  status: user.status,
})))

async function loadRoles() {
  try {
    const response = await listResource<Role>('/roles', { per_page: 100 })
    roles.value = response.data
  } catch {
    roles.value = []
  }
}

async function loadUsers(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<User>('/users', {
      page,
      search: search.value || undefined,
      filter: {
        status: statusFilter.value,
        role: roleFilter.value,
      },
    })

    users.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

function submitFilters() {
  void loadUsers(1)
}

async function removeUser(id: number) {
  const target = users.value.find((user) => user.id === id)

  if (!target || !globalThis.confirm(`Deactivate ${target.name}?`)) {
    return
  }

  try {
    await destroyResource(`/users/${id}`)
    await loadUsers(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

onMounted(async () => {
  await Promise.all([loadRoles(), loadUsers()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Identity"
      title="Users"
      description="Tenant-aware user administration with role assignments, status management, and a consistent API contract."
    >
      <template #actions>
        <RouterLink
          v-if="canCreateUsers"
          :to="{ name: 'users.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Add user
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load users"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="submitFilters">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search users"
          class="min-w-[220px] flex-1 rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option
            v-for="option in userStatusOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="roleFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All roles</option>
          <option
            v-for="role in roles"
            :key="role.id"
            :value="role.slug"
          >
            {{ role.name }}
          </option>
        </select>
        <button
          type="submit"
          class="rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Apply
        </button>
      </FilterBar>
    </form>

    <DataTable
      :columns="columns"
      :rows="rows"
      :loading="loading"
      empty-title="No users yet"
      empty-description="Create the first tenant user and assign a role template."
    >
      <template #cell-status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template
        v-if="canManageUsers"
        #cell-actions="{ row }"
      >
        <div class="flex items-center gap-2">
          <RouterLink
            v-if="auth.hasPermission('users.update')"
            :to="{ name: 'users.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
          >
            Edit
          </RouterLink>
          <button
            v-if="auth.hasPermission('users.delete') && row.id !== auth.user?.id"
            type="button"
            class="rounded-xl border border-rose-300 dark:border-rose-800/60 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:text-rose-200 transition hover:bg-rose-50 dark:hover:bg-rose-950/40"
            @click="removeUser(Number(row.id))"
          >
            Deactivate
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadUsers(meta.current_page - 1)"
      @next="loadUsers(meta.current_page + 1)"
    />
  </div>
</template>
