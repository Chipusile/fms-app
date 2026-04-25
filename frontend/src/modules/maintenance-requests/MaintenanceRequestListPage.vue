<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import {
  maintenanceRequestPriorityOptions,
  maintenanceRequestStatusOptions,
  maintenanceRequestTypeOptions,
} from '@/lib/fleet-options'
import { destroyResource, getResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  MaintenanceRequest,
  MaintenanceRequestSupportData,
  PaginationMeta,
  ReferenceOption,
} from '@/types'

const auth = useAuthStore()
const requests = ref<MaintenanceRequest[]>([])
const vehicles = ref<ReferenceOption[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const typeFilter = ref('')
const vehicleFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const canCreate = computed(() => auth.hasPermission('maintenance.create'))
const canManage = computed(() => auth.hasAnyPermission(['maintenance.update', 'maintenance.delete', 'maintenance.approve']))

const columns = computed(() => (
  canManage.value
    ? [
        { key: 'request_number', label: 'Request' },
        { key: 'title', label: 'Title' },
        { key: 'vehicle', label: 'Vehicle' },
        { key: 'request_type', label: 'Type' },
        { key: 'priority', label: 'Priority' },
        { key: 'needed_by', label: 'Needed by' },
        { key: 'status', label: 'Status' },
        { key: 'actions', label: 'Actions' },
      ]
    : [
        { key: 'request_number', label: 'Request' },
        { key: 'title', label: 'Title' },
        { key: 'vehicle', label: 'Vehicle' },
        { key: 'request_type', label: 'Type' },
        { key: 'priority', label: 'Priority' },
        { key: 'needed_by', label: 'Needed by' },
        { key: 'status', label: 'Status' },
      ]
))

const rows = computed(() => requests.value.map((record) => ({
  id: record.id,
  request_number: record.request_number,
  title: record.title,
  vehicle: record.vehicle?.registration_number ?? 'Unknown vehicle',
  request_type: record.request_type,
  priority: record.priority,
  needed_by: formatDate(record.needed_by),
  status: record.status,
})))

const statusCounts = computed(() => ({
  submitted: requests.value.filter((item) => item.status === 'submitted').length,
  approved: requests.value.filter((item) => item.status === 'approved').length,
  converted: requests.value.filter((item) => item.status === 'converted').length,
}))

function formatDate(value: string | null): string {
  if (!value) return '—'

  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
}

async function loadSupportData() {
  try {
    const data = await getResource<MaintenanceRequestSupportData>('/maintenance-requests/support-data')
    vehicles.value = data.vehicles
  } catch {
    vehicles.value = []
  }
}

async function loadRequests(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<MaintenanceRequest>('/maintenance-requests', {
      page,
      search: search.value || undefined,
      filter: {
        status: statusFilter.value,
        request_type: typeFilter.value,
        vehicle_id: vehicleFilter.value,
      },
    })

    requests.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function removeRequest(id: number) {
  const target = requests.value.find((item) => item.id === id)

  if (!target || !globalThis.confirm(`Delete maintenance request ${target.request_number}?`)) {
    return
  }

  try {
    await destroyResource(`/maintenance-requests/${id}`)
    await loadRequests(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

onMounted(async () => {
  await Promise.all([loadSupportData(), loadRequests()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Maintenance"
      title="Maintenance Requests"
      description="Capture maintenance demand before execution starts, keeping approvals, prioritization, and work-order conversion tenant-configurable."
    >
      <template #actions>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'maintenance-requests.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Create request
        </RouterLink>
      </template>
    </PageHeader>

    <div class="grid gap-4 md:grid-cols-4">
      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/70 p-5 shadow-sm shadow-slate-200/60 dark:shadow-black/20">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Tracked requests</p>
        <p class="mt-3 text-3xl font-semibold text-slate-950 dark:text-slate-100">{{ meta.total }}</p>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Visible maintenance demand records for the active tenant.</p>
      </div>
      <div class="rounded-2xl border border-amber-200 dark:border-amber-900/60 bg-amber-50/70 dark:bg-amber-950/40 p-5 shadow-sm shadow-amber-100/60 dark:shadow-amber-900/20">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-200">Submitted</p>
        <p class="mt-3 text-3xl font-semibold text-amber-950 dark:text-amber-100">{{ statusCounts.submitted }}</p>
        <p class="mt-2 text-sm text-amber-900/80">Requests still waiting for review or action.</p>
      </div>
      <div class="rounded-2xl border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50/70 dark:bg-emerald-950/40 p-5 shadow-sm shadow-emerald-100/60 dark:shadow-emerald-900/20">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-200">Approved</p>
        <p class="mt-3 text-3xl font-semibold text-emerald-950 dark:text-emerald-100">{{ statusCounts.approved }}</p>
        <p class="mt-2 text-sm text-emerald-900/80">Requests cleared for conversion into execution work.</p>
      </div>
      <div class="rounded-2xl border border-sky-200 dark:border-sky-900/60 bg-sky-50/70 dark:bg-sky-950/40 p-5 shadow-sm shadow-sky-100/60 dark:shadow-sky-900/20">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-200">Converted</p>
        <p class="mt-3 text-3xl font-semibold text-sky-950 dark:text-sky-100">{{ statusCounts.converted }}</p>
        <p class="mt-2 text-sm text-sky-900/80">Requests already linked to work orders for execution tracking.</p>
      </div>
    </div>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load maintenance requests"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadRequests(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search request number, title, or vehicle"
          class="min-w-[240px] flex-1 rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option v-for="option in maintenanceRequestStatusOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="typeFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All request types</option>
          <option v-for="option in maintenanceRequestTypeOptions" :key="option.value" :value="option.value">
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
      empty-title="No maintenance requests available"
      empty-description="Create the first request to formalize demand capture before opening maintenance work."
    >
      <template #cell-request_type="{ value }">
        <span class="capitalize">{{ String(value).replaceAll('_', ' ') }}</span>
      </template>
      <template #cell-priority="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template #cell-status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template v-if="canManage" #cell-actions="{ row }">
        <div class="flex items-center gap-2">
          <RouterLink
            :to="{ name: 'maintenance-requests.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
          >
            Open
          </RouterLink>
          <button
            v-if="auth.hasPermission('maintenance.delete') && row.status !== 'converted'"
            type="button"
            class="rounded-xl border border-rose-300 dark:border-rose-800/60 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:text-rose-200 transition hover:bg-rose-50 dark:hover:bg-rose-950/40"
            @click="removeRequest(Number(row.id))"
          >
            Delete
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadRequests(meta.current_page - 1)"
      @next="loadRequests(meta.current_page + 1)"
    />
  </div>
</template>
