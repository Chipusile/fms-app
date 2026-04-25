<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import { destroyResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type { ApiError, PaginationMeta, Role } from '@/types'

const auth = useAuthStore()
const baseColumns = [
  { key: 'name', label: 'Role' },
  { key: 'slug', label: 'Key' },
  { key: 'permissions', label: 'Permissions' },
  { key: 'users', label: 'Users' },
]

const roles = ref<Role[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const meta = ref<PaginationMeta>({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
})
const canCreateRoles = computed(() => auth.hasPermission('roles.create'))
const canEditRoles = computed(() => auth.hasPermission('roles.update'))
const canDeleteRoles = computed(() => auth.hasPermission('roles.delete'))
const canManageRoles = computed(() => auth.hasAnyPermission(['roles.update', 'roles.delete']))
const columns = computed(() => (
  canManageRoles.value
    ? [...baseColumns, { key: 'actions', label: 'Actions' }]
    : baseColumns
))

const rows = computed(() => roles.value.map((role) => ({
  id: role.id,
  name: role.name,
  slug: role.slug,
  permissions: role.permissions?.length ?? 0,
  users: role.users_count ?? 0,
  is_system: role.is_system,
})))

async function loadRoles(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<Role>('/roles', {
      page,
      search: search.value || undefined,
    })

    roles.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadRoles()
})

async function removeRole(id: number) {
  const target = roles.value.find((role) => role.id === id)

  if (!target || target.is_system || !globalThis.confirm(`Delete ${target.name}?`)) {
    return
  }

  try {
    await destroyResource(`/roles/${id}`)
    await loadRoles(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Access"
      title="Roles"
      description="Roles are tenant-scoped containers for permissions. Business logic must never depend on role names directly."
    >
      <template #actions>
        <RouterLink
          v-if="canCreateRoles"
          :to="{ name: 'roles.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Create role
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load roles"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadRoles(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search roles"
          class="min-w-[220px] flex-1 rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
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
      empty-title="No roles available"
      empty-description="Seed the default role templates or create a custom tenant role."
    >
      <template
        v-if="canManageRoles"
        #cell-actions="{ row }"
      >
        <div class="flex items-center gap-2">
          <RouterLink
            v-if="canEditRoles && !row.is_system"
            :to="{ name: 'roles.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
          >
            Edit
          </RouterLink>
          <span
            v-else-if="row.is_system"
            class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-3 py-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400"
          >
            System role
          </span>
          <button
            v-if="canDeleteRoles && !row.is_system"
            type="button"
            class="rounded-xl border border-rose-300 dark:border-rose-800/60 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:text-rose-200 transition hover:bg-rose-50 dark:hover:bg-rose-950/40"
            @click="removeRole(Number(row.id))"
          >
            Delete
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadRoles(meta.current_page - 1)"
      @next="loadRoles(meta.current_page + 1)"
    />
  </div>
</template>
