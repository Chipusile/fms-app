<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { tripStatusOptions } from '@/lib/fleet-options'
import { getResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type { ApiError, PaginationMeta, ReferenceOption, Trip, TripSupportData } from '@/types'

const auth = useAuthStore()
const trips = ref<Trip[]>([])
const vehicles = ref<ReferenceOption[]>([])
const drivers = ref<ReferenceOption[]>([])
const tripApprovalRequired = ref(true)
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const vehicleFilter = ref('')
const driverFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const canCreate = computed(() => auth.hasPermission('trips.create'))

const columns = [
  { key: 'trip_number', label: 'Trip' },
  { key: 'route', label: 'Route' },
  { key: 'schedule', label: 'Schedule' },
  { key: 'vehicle', label: 'Vehicle' },
  { key: 'driver', label: 'Driver' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Actions' },
]

const rows = computed(() => trips.value.map((trip) => ({
  id: trip.id,
  trip_number: trip.trip_number,
  route: `${trip.origin} → ${trip.destination}`,
  schedule: formatRange(trip.scheduled_start, trip.scheduled_end),
  vehicle: trip.vehicle?.registration_number ?? 'Unassigned',
  driver: trip.driver?.name ?? 'Unassigned',
  status: trip.status,
})))

function formatDateTime(value: string | null): string {
  if (!value) return '—'

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function formatRange(start: string, end: string): string {
  return `${formatDateTime(start)} to ${formatDateTime(end)}`
}

async function loadSupportData() {
  try {
    const data = await getResource<TripSupportData>('/trips/support-data')
    vehicles.value = data.vehicles
    drivers.value = data.drivers
    tripApprovalRequired.value = data.trip_approval_required
  } catch {
    vehicles.value = []
    drivers.value = []
    tripApprovalRequired.value = true
  }
}

async function loadTrips(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<Trip>('/trips', {
      page,
      search: search.value || undefined,
      filter: {
        status: statusFilter.value,
        vehicle_id: vehicleFilter.value,
        driver_id: driverFilter.value,
      },
    })

    trips.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadSupportData(), loadTrips()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Operations"
      title="Trips"
      description="Trip requests and journey execution records manage dispatch, approvals, and route accountability across the fleet."
    >
      <template #actions>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'trips.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Create trip
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="tripApprovalRequired"
      title="Trip approval is enabled"
      description="New trip requests remain in requested status until an authorized approver approves them."
      tone="info"
    />

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load trips"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadTrips(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search trip number, route, vehicle, or driver"
          class="min-w-[240px] flex-1 rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option v-for="option in tripStatusOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="vehicleFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All vehicles</option>
          <option v-for="vehicle in vehicles" :key="vehicle.id" :value="String(vehicle.id)">
            {{ vehicle.label }}
          </option>
        </select>
        <select
          v-model="driverFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All drivers</option>
          <option v-for="driver in drivers" :key="driver.id" :value="String(driver.id)">
            {{ driver.label }}
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
      empty-title="No trips scheduled"
      empty-description="Create the first trip request to begin operational journey tracking."
    >
      <template #cell-status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template #cell-actions="{ row }">
        <RouterLink
          :to="{ name: 'trips.edit', params: { id: String(row.id) } }"
          class="inline-flex rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
        >
          Open
        </RouterLink>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadTrips(meta.current_page - 1)"
      @next="loadTrips(meta.current_page + 1)"
    />
  </div>
</template>
