<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { vehicleFuelTypeOptions, vehicleStatusOptions } from '@/lib/fleet-options'
import { destroyResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type { ApiError, PaginationMeta, Vehicle, VehicleType } from '@/types'

const auth = useAuthStore()
const baseColumns = [
  { key: 'registration_number', label: 'Registration' },
  { key: 'vehicle', label: 'Vehicle' },
  { key: 'type', label: 'Type' },
  { key: 'odometer_reading', label: 'Odometer' },
  { key: 'status', label: 'Status' },
]

const vehicles = ref<Vehicle[]>([])
const vehicleTypes = ref<VehicleType[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const fuelTypeFilter = ref('')
const vehicleTypeFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const canCreate = computed(() => auth.hasPermission('vehicles.create'))
const canManage = computed(() => auth.hasAnyPermission(['vehicles.update', 'vehicles.delete']))
const canManageAssignments = computed(() => auth.hasPermission('vehicles.assign'))
const canAccessTemplates = computed(() => auth.hasAnyPermission(['vehicles.view', 'drivers.view', 'vehicles.create', 'drivers.create']))
const columns = computed(() => (
  canManage.value ? [...baseColumns, { key: 'actions', label: 'Actions' }] : baseColumns
))

const rows = computed(() => vehicles.value.map((vehicle) => ({
  id: vehicle.id,
  registration_number: vehicle.registration_number,
  vehicle: `${vehicle.make} ${vehicle.model}`,
  type: vehicle.type?.name ?? 'Unassigned',
  odometer_reading: vehicle.odometer_reading.toLocaleString(),
  status: vehicle.status,
})))

async function loadVehicleTypes() {
  try {
    const response = await listResource<VehicleType>('/vehicle-types', { per_page: 100 })
    vehicleTypes.value = response.data
  } catch {
    vehicleTypes.value = []
  }
}

async function loadVehicles(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<Vehicle>('/vehicles', {
      page,
      search: search.value || undefined,
      filter: {
        status: statusFilter.value,
        fuel_type: fuelTypeFilter.value,
        vehicle_type_id: vehicleTypeFilter.value,
      },
    })

    vehicles.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function removeVehicle(id: number) {
  const target = vehicles.value.find((item) => item.id === id)

  if (!target || !globalThis.confirm(`Delete ${target.registration_number}?`)) {
    return
  }

  try {
    await destroyResource(`/vehicles/${id}`)
    await loadVehicles(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

onMounted(async () => {
  await Promise.all([loadVehicleTypes(), loadVehicles()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Fleet"
      title="Vehicles"
      description="Vehicle records are the operational source of truth for asset identity, categorisation, lifecycle state, and allocation readiness."
    >
      <template #actions>
        <RouterLink
          v-if="canManageAssignments"
          :to="{ name: 'vehicle-assignments' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
        >
          Manage assignments
        </RouterLink>
        <RouterLink
          v-if="canAccessTemplates"
          :to="{ name: 'onboarding-templates' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
        >
          Onboarding templates
        </RouterLink>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'vehicles.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Add vehicle
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load vehicles"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadVehicles(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search vehicles"
          class="min-w-[220px] flex-1 rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option
            v-for="option in vehicleStatusOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="fuelTypeFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All fuel types</option>
          <option
            v-for="option in vehicleFuelTypeOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="vehicleTypeFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All vehicle types</option>
          <option
            v-for="type in vehicleTypes"
            :key="type.id"
            :value="String(type.id)"
          >
            {{ type.name }}
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
      empty-title="No vehicles registered"
      empty-description="Register the first fleet assets after defining vehicle types and departments."
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
            v-if="auth.hasPermission('vehicles.update')"
            :to="{ name: 'vehicles.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
          >
            Edit
          </RouterLink>
          <button
            v-if="auth.hasPermission('vehicles.delete')"
            type="button"
            class="rounded-xl border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
            @click="removeVehicle(Number(row.id))"
          >
            Delete
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadVehicles(meta.current_page - 1)"
      @next="loadVehicles(meta.current_page + 1)"
    />
  </div>
</template>
