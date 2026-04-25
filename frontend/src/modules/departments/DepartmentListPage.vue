<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { departmentStatusOptions } from '@/lib/fleet-options'
import { destroyResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type { ApiError, Department, PaginationMeta } from '@/types'

const auth = useAuthStore()
const baseColumns = [
  { key: 'name', label: 'Department' },
  { key: 'code', label: 'Code' },
  { key: 'manager', label: 'Manager' },
  { key: 'status', label: 'Status' },
]

const departments = ref<Department[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const canCreate = computed(() => auth.hasPermission('departments.create'))
const canManage = computed(() => auth.hasAnyPermission(['departments.update', 'departments.delete']))
const columns = computed(() => (
  canManage.value ? [...baseColumns, { key: 'actions', label: 'Actions' }] : baseColumns
))

const rows = computed(() => departments.value.map((department) => ({
  id: department.id,
  name: department.name,
  code: department.code,
  manager: department.manager?.name ?? 'Unassigned',
  status: department.status,
})))

async function loadDepartments(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<Department>('/departments', {
      page,
      search: search.value || undefined,
      filter: {
        status: statusFilter.value,
      },
    })

    departments.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function removeDepartment(id: number) {
  const target = departments.value.find((item) => item.id === id)

  if (!target || !globalThis.confirm(`Delete ${target.name}?`)) {
    return
  }

  try {
    await destroyResource(`/departments/${id}`)
    await loadDepartments(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

onMounted(async () => {
  await loadDepartments()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Master Data"
      title="Departments"
      description="Departments and cost centres anchor operational ownership, allocation, and reporting segmentation."
    >
      <template #actions>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'departments.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Add department
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load departments"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadDepartments(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search departments"
          class="min-w-[220px] flex-1 rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option
            v-for="option in departmentStatusOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
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
      empty-title="No departments configured"
      empty-description="Create departments before assigning vehicles or drivers to operating units."
    >
      <template #cell-status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template
        v-if="canManage"
        #cell-actions="{ row }"
      >
        <div class="flex items-center gap-2">
          <RouterLink
            v-if="auth.hasPermission('departments.update')"
            :to="{ name: 'departments.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
          >
            Edit
          </RouterLink>
          <button
            v-if="auth.hasPermission('departments.delete')"
            type="button"
            class="rounded-xl border border-rose-300 dark:border-rose-800/60 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:text-rose-200 transition hover:bg-rose-50 dark:hover:bg-rose-950/40"
            @click="removeDepartment(Number(row.id))"
          >
            Delete
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadDepartments(meta.current_page - 1)"
      @next="loadDepartments(meta.current_page + 1)"
    />
  </div>
</template>
