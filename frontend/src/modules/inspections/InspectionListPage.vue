<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { inspectionResultOptions, inspectionStatusOptions } from '@/lib/fleet-options'
import { getResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  Inspection,
  InspectionSupportData,
  PaginationMeta,
  TripSupportVehicleOption,
} from '@/types'

const auth = useAuthStore()
const inspections = ref<Inspection[]>([])
const vehicles = ref<TripSupportVehicleOption[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const resultFilter = ref('')
const vehicleFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const canCreate = computed(() => auth.hasPermission('inspections.create'))

const columns = [
  { key: 'inspection_number', label: 'Inspection' },
  { key: 'template', label: 'Template' },
  { key: 'vehicle', label: 'Vehicle' },
  { key: 'performed_at', label: 'Performed at' },
  { key: 'result', label: 'Result' },
  { key: 'status', label: 'Status' },
  { key: 'defects', label: 'Defects' },
  { key: 'actions', label: 'Actions' },
]

const rows = computed(() => inspections.value.map((inspection) => ({
  id: inspection.id,
  inspection_number: inspection.inspection_number,
  template: inspection.template?.name ?? 'Unknown template',
  vehicle: inspection.vehicle?.registration_number ?? 'Unknown vehicle',
  performed_at: formatDateTime(inspection.performed_at),
  result: inspection.result,
  status: inspection.status,
  defects: `${inspection.failed_items}/${inspection.total_items} · critical ${inspection.critical_defects}`,
})))

function formatDateTime(value: string | null): string {
  if (!value) return '—'

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

async function loadSupportData() {
  try {
    const data = await getResource<InspectionSupportData>('/inspections/support-data')
    vehicles.value = data.vehicles
  } catch {
    vehicles.value = []
  }
}

async function loadInspections(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<Inspection>('/inspections', {
      page,
      search: search.value || undefined,
      filter: {
        status: statusFilter.value,
        result: resultFilter.value,
        vehicle_id: vehicleFilter.value,
      },
    })

    inspections.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadSupportData(), loadInspections()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Operations"
      title="Inspections"
      description="Recorded inspections capture checklist outcomes, odometer checkpoints, and governance triggers for defects that need follow-up."
    >
      <template #actions>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'inspections.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Record inspection
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load inspections"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadInspections(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search inspection number, template, or vehicle"
          class="min-w-[240px] flex-1 rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option v-for="option in inspectionStatusOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="resultFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All results</option>
          <option v-for="option in inspectionResultOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="vehicleFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All vehicles</option>
          <option v-for="vehicle in vehicles" :key="vehicle.id" :value="String(vehicle.id)">
            {{ vehicle.label }}
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
      empty-title="No inspections recorded"
      empty-description="Record the first inspection to begin checklist-based operational governance."
    >
      <template #cell-result="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template #cell-status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template #cell-actions="{ row }">
        <RouterLink
          :to="{ name: 'inspections.show', params: { id: String(row.id) } }"
          class="inline-flex rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
        >
          Open
        </RouterLink>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadInspections(meta.current_page - 1)"
      @next="loadInspections(meta.current_page + 1)"
    />
  </div>
</template>
