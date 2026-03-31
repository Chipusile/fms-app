<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { driverStatusOptions } from '@/lib/fleet-options'
import { destroyResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type { ApiError, Driver, PaginationMeta } from '@/types'

const auth = useAuthStore()
const baseColumns = [
  { key: 'name', label: 'Driver' },
  { key: 'license_number', label: 'License' },
  { key: 'department', label: 'Department' },
  { key: 'status', label: 'Status' },
]

const drivers = ref<Driver[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const canCreate = computed(() => auth.hasPermission('drivers.create'))
const canManage = computed(() => auth.hasAnyPermission(['drivers.update', 'drivers.delete']))
const canAccessTemplates = computed(() => auth.hasAnyPermission(['vehicles.view', 'drivers.view', 'vehicles.create', 'drivers.create']))
const columns = computed(() => (
  canManage.value ? [...baseColumns, { key: 'actions', label: 'Actions' }] : baseColumns
))

const rows = computed(() => drivers.value.map((driver) => ({
  id: driver.id,
  name: driver.name,
  license_number: driver.license_number,
  department: driver.department?.name ?? 'Unassigned',
  status: driver.status,
})))

async function loadDrivers(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<Driver>('/drivers', {
      page,
      search: search.value || undefined,
      filter: {
        status: statusFilter.value,
      },
    })

    drivers.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function removeDriver(id: number) {
  const target = drivers.value.find((item) => item.id === id)

  if (!target || !globalThis.confirm(`Delete ${target.name}?`)) {
    return
  }

  try {
    await destroyResource(`/drivers/${id}`)
    await loadDrivers(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

onMounted(async () => {
  await loadDrivers()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Operations"
      title="Drivers"
      description="Drivers remain tenant-scoped operational records whether or not they are linked to a full application user account."
    >
      <template #actions>
        <RouterLink
          v-if="canAccessTemplates"
          :to="{ name: 'onboarding-templates' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
        >
          Onboarding templates
        </RouterLink>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'drivers.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Add driver
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load drivers"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadDrivers(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search drivers"
          class="min-w-[220px] flex-1 rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option
            v-for="option in driverStatusOptions"
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
      empty-title="No drivers configured"
      empty-description="Register operational drivers before vehicle allocation and trip workflows begin."
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
            v-if="auth.hasPermission('drivers.update')"
            :to="{ name: 'drivers.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
          >
            Edit
          </RouterLink>
          <button
            v-if="auth.hasPermission('drivers.delete')"
            type="button"
            class="rounded-xl border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
            @click="removeDriver(Number(row.id))"
          >
            Delete
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadDrivers(meta.current_page - 1)"
      @next="loadDrivers(meta.current_page + 1)"
    />
  </div>
</template>
