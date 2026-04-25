<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import { destroyResource, getResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type { ApiError, FuelLog, FuelLogSupportData, PaginationMeta, ReferenceOption } from '@/types'

const auth = useAuthStore()
const fuelLogs = ref<FuelLog[]>([])
const vehicles = ref<ReferenceOption[]>([])
const drivers = ref<ReferenceOption[]>([])
const fuelTypes = ref<string[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const vehicleFilter = ref('')
const driverFilter = ref('')
const fuelTypeFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const canCreate = computed(() => auth.hasPermission('fuel.create'))
const canManage = computed(() => auth.hasAnyPermission(['fuel.update', 'fuel.delete']))

const columns = computed(() => (
  canManage.value
    ? [
        { key: 'reference_number', label: 'Reference' },
        { key: 'vehicle', label: 'Vehicle' },
        { key: 'driver', label: 'Driver' },
        { key: 'quantity_liters', label: 'Litres' },
        { key: 'total_cost', label: 'Total cost' },
        { key: 'fueled_at', label: 'Fueled at' },
        { key: 'actions', label: 'Actions' },
      ]
    : [
        { key: 'reference_number', label: 'Reference' },
        { key: 'vehicle', label: 'Vehicle' },
        { key: 'driver', label: 'Driver' },
        { key: 'quantity_liters', label: 'Litres' },
        { key: 'total_cost', label: 'Total cost' },
        { key: 'fueled_at', label: 'Fueled at' },
      ]
))

const rows = computed(() => fuelLogs.value.map((fuelLog) => ({
  id: fuelLog.id,
  reference_number: fuelLog.reference_number ?? 'Unreferenced',
  vehicle: fuelLog.vehicle?.registration_number ?? 'Unknown vehicle',
  driver: fuelLog.driver?.name ?? 'No driver',
  quantity_liters: Number(fuelLog.quantity_liters).toFixed(2),
  total_cost: Number(fuelLog.total_cost).toFixed(2),
  fueled_at: formatDateTime(fuelLog.fueled_at),
})))

function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

async function loadSupportData() {
  try {
    const data = await getResource<FuelLogSupportData>('/fuel-logs/support-data')
    vehicles.value = data.vehicles
    drivers.value = data.drivers
    fuelTypes.value = data.fuel_types
  } catch {
    vehicles.value = []
    drivers.value = []
    fuelTypes.value = []
  }
}

async function loadFuelLogs(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<FuelLog>('/fuel-logs', {
      page,
      search: search.value || undefined,
      filter: {
        vehicle_id: vehicleFilter.value,
        driver_id: driverFilter.value,
        fuel_type: fuelTypeFilter.value,
      },
    })

    fuelLogs.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function removeFuelLog(id: number) {
  const target = fuelLogs.value.find((item) => item.id === id)

  if (!target || !globalThis.confirm(`Delete fuel log ${target.reference_number ?? target.id}?`)) {
    return
  }

  try {
    await destroyResource(`/fuel-logs/${id}`)
    await loadFuelLogs(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

onMounted(async () => {
  await Promise.all([loadSupportData(), loadFuelLogs()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Operations"
      title="Fuel Logs"
      description="Capture fueling activity, odometer checkpoints, and supplier references for cost and consumption analysis."
    >
      <template #actions>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'fuel-logs.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Record fuel log
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load fuel logs"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadFuelLogs(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search reference, vehicle, trip, or station"
          class="min-w-[240px] flex-1 rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="vehicleFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All vehicles</option>
          <option v-for="vehicle in vehicles" :key="vehicle.id" :value="String(vehicle.id)">
            {{ vehicle.label }}
          </option>
        </select>
        <select
          v-model="driverFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All drivers</option>
          <option v-for="driver in drivers" :key="driver.id" :value="String(driver.id)">
            {{ driver.label }}
          </option>
        </select>
        <select
          v-model="fuelTypeFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All fuel types</option>
          <option v-for="fuelType in fuelTypes" :key="fuelType" :value="fuelType">
            {{ fuelType.replaceAll('_', ' ') }}
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
      empty-title="No fuel activity logged"
      empty-description="Record the first fueling event to begin mileage and fuel-cost tracking."
    >
      <template
        v-if="canManage"
        #cell-actions="{ row }"
      >
        <div class="flex items-center gap-2">
          <RouterLink
            v-if="auth.hasPermission('fuel.update')"
            :to="{ name: 'fuel-logs.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
          >
            Edit
          </RouterLink>
          <button
            v-if="auth.hasPermission('fuel.delete')"
            type="button"
            class="rounded-xl border border-rose-300 dark:border-rose-800/60 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:text-rose-200 transition hover:bg-rose-50 dark:hover:bg-rose-950/40"
            @click="removeFuelLog(Number(row.id))"
          >
            Delete
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadFuelLogs(meta.current_page - 1)"
      @next="loadFuelLogs(meta.current_page + 1)"
    />
  </div>
</template>
