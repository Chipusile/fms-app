<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { incidentSeverityOptions, incidentStatusOptions, incidentTypeOptions } from '@/lib/fleet-options'
import { destroyResource, getResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type { ApiError, Incident, IncidentSupportData, PaginationMeta, ReferenceOption } from '@/types'

const auth = useAuthStore()
const incidents = ref<Incident[]>([])
const vehicles = ref<ReferenceOption[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const severityFilter = ref('')
const typeFilter = ref('')
const vehicleFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const canCreate = computed(() => auth.hasPermission('incidents.create'))
const canManage = computed(() => auth.hasAnyPermission(['incidents.update', 'incidents.delete']))
const columns = computed(() => (
  canManage.value
    ? [
        { key: 'incident_number', label: 'Incident' },
        { key: 'incident_type', label: 'Type' },
        { key: 'severity', label: 'Severity' },
        { key: 'vehicle', label: 'Vehicle' },
        { key: 'occurred_at', label: 'Occurred at' },
        { key: 'status', label: 'Status' },
        { key: 'actions', label: 'Actions' },
      ]
    : [
        { key: 'incident_number', label: 'Incident' },
        { key: 'incident_type', label: 'Type' },
        { key: 'severity', label: 'Severity' },
        { key: 'vehicle', label: 'Vehicle' },
        { key: 'occurred_at', label: 'Occurred at' },
        { key: 'status', label: 'Status' },
        { key: 'actions', label: 'Actions' },
      ]
))

const rows = computed(() => incidents.value.map((incident) => ({
  id: incident.id,
  incident_number: incident.incident_number,
  incident_type: incident.incident_type,
  severity: incident.severity,
  vehicle: incident.vehicle?.registration_number ?? 'Unknown vehicle',
  occurred_at: formatDateTime(incident.occurred_at),
  status: incident.status,
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
    const data = await getResource<IncidentSupportData>('/incidents/support-data')
    vehicles.value = data.vehicles
  } catch {
    vehicles.value = []
  }
}

async function loadIncidents(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<Incident>('/incidents', {
      page,
      search: search.value || undefined,
      filter: {
        status: statusFilter.value,
        severity: severityFilter.value,
        incident_type: typeFilter.value,
        vehicle_id: vehicleFilter.value,
      },
    })

    incidents.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function removeIncident(id: number) {
  const incident = incidents.value.find((item) => item.id === id)

  if (!incident || !globalThis.confirm(`Delete incident ${incident.incident_number}?`)) {
    return
  }

  try {
    await destroyResource(`/incidents/${id}`)
    await loadIncidents(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

onMounted(async () => {
  await Promise.all([loadSupportData(), loadIncidents()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Operations"
      title="Incidents"
      description="Incident records centralize operational exceptions, severity handling, and review workflows across vehicles, drivers, and trips."
    >
      <template #actions>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'incidents.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Report incident
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load incidents"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadIncidents(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search incident number, location, or vehicle"
          class="min-w-[240px] flex-1 rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option v-for="option in incidentStatusOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="severityFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All severities</option>
          <option v-for="option in incidentSeverityOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="typeFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All types</option>
          <option v-for="option in incidentTypeOptions" :key="option.value" :value="option.value">
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
      empty-title="No incidents reported"
      empty-description="Operational incidents, exceptions, and safety events will appear here."
    >
      <template #cell-incident_type="{ value }">
        {{ String(value).replaceAll('_', ' ') }}
      </template>
      <template #cell-severity="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template #cell-status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template #cell-actions="{ row }">
        <div class="flex items-center gap-2">
          <RouterLink
            :to="{ name: 'incidents.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
          >
            Open
          </RouterLink>
          <button
            v-if="auth.hasPermission('incidents.delete')"
            type="button"
            class="rounded-xl border border-rose-300 dark:border-rose-800/60 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:text-rose-200 transition hover:bg-rose-50 dark:hover:bg-rose-950/40"
            @click="removeIncident(Number(row.id))"
          >
            Delete
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadIncidents(meta.current_page - 1)"
      @next="loadIncidents(meta.current_page + 1)"
    />
  </div>
</template>
