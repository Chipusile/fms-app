<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { destroyResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type { ApiError, PaginationMeta, VehicleType } from '@/types'

const auth = useAuthStore()
const baseColumns = [
  { key: 'name', label: 'Vehicle type' },
  { key: 'code', label: 'Code' },
  { key: 'default_fuel_type', label: 'Default fuel' },
  { key: 'default_service_interval_km', label: 'Service interval (km)' },
  { key: 'status', label: 'Status' },
]

const vehicleTypes = ref<VehicleType[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const activeFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const canCreate = computed(() => auth.hasPermission('vehicle-types.create'))
const canManage = computed(() => auth.hasAnyPermission(['vehicle-types.update', 'vehicle-types.delete']))
const columns = computed(() => (
  canManage.value ? [...baseColumns, { key: 'actions', label: 'Actions' }] : baseColumns
))

const rows = computed(() => vehicleTypes.value.map((vehicleType) => ({
  id: vehicleType.id,
  name: vehicleType.name,
  code: vehicleType.code,
  default_fuel_type: vehicleType.default_fuel_type ?? '—',
  default_service_interval_km: vehicleType.default_service_interval_km ?? '—',
  status: vehicleType.is_active ? 'active' : 'inactive',
})))

async function loadVehicleTypes(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<VehicleType>('/vehicle-types', {
      page,
      search: search.value || undefined,
      filter: {
        is_active: activeFilter.value,
      },
    })

    vehicleTypes.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function removeVehicleType(id: number) {
  const target = vehicleTypes.value.find((item) => item.id === id)

  if (!target || !globalThis.confirm(`Delete ${target.name}?`)) {
    return
  }

  try {
    await destroyResource(`/vehicle-types/${id}`)
    await loadVehicleTypes(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

onMounted(async () => {
  await loadVehicleTypes()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Fleet"
      title="Vehicle Types"
      description="Tenant-scoped vehicle type catalogues keep fleet setup configurable without hardcoded asset categories."
    >
      <template #actions>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'vehicle-types.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Add vehicle type
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load vehicle types"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadVehicleTypes(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search vehicle types"
          class="min-w-[220px] flex-1 rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="activeFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option value="true">Active</option>
          <option value="false">Inactive</option>
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
      empty-title="No vehicle types configured"
      empty-description="Create the initial vehicle catalogues before registering fleet assets."
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
            v-if="auth.hasPermission('vehicle-types.update')"
            :to="{ name: 'vehicle-types.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
          >
            Edit
          </RouterLink>
          <button
            v-if="auth.hasPermission('vehicle-types.delete')"
            type="button"
            class="rounded-xl border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
            @click="removeVehicleType(Number(row.id))"
          >
            Delete
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadVehicleTypes(meta.current_page - 1)"
      @next="loadVehicleTypes(meta.current_page + 1)"
    />
  </div>
</template>
