<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { maintenanceScheduleStatusOptions, maintenanceScheduleTypeOptions } from '@/lib/fleet-options'
import { destroyResource, getResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  MaintenanceSchedule,
  MaintenanceScheduleSupportData,
  PaginationMeta,
  ReferenceOption,
} from '@/types'

const auth = useAuthStore()
const schedules = ref<MaintenanceSchedule[]>([])
const vehicles = ref<ReferenceOption[]>([])
const upcomingSchedules = ref<MaintenanceSchedule[]>([])
const overdueSchedules = ref<MaintenanceSchedule[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const typeFilter = ref('')
const vehicleFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const canCreate = computed(() => auth.hasPermission('maintenance.create'))
const canManage = computed(() => auth.hasAnyPermission(['maintenance.update', 'maintenance.delete']))

const columns = computed(() => (
  canManage.value
    ? [
        { key: 'title', label: 'Schedule' },
        { key: 'vehicle', label: 'Vehicle' },
        { key: 'interval', label: 'Interval' },
        { key: 'next_due', label: 'Next due' },
        { key: 'due_status', label: 'Due status' },
        { key: 'status', label: 'Status' },
        { key: 'actions', label: 'Actions' },
      ]
    : [
        { key: 'title', label: 'Schedule' },
        { key: 'vehicle', label: 'Vehicle' },
        { key: 'interval', label: 'Interval' },
        { key: 'next_due', label: 'Next due' },
        { key: 'due_status', label: 'Due status' },
        { key: 'status', label: 'Status' },
      ]
))

const rows = computed(() => schedules.value.map((schedule) => ({
  id: schedule.id,
  title: schedule.title,
  vehicle: schedule.vehicle?.registration_number ?? 'Unknown vehicle',
  interval: formatInterval(schedule.interval_days, schedule.interval_km),
  next_due: formatNextDue(schedule),
  due_status: schedule.due_status,
  status: schedule.status,
})))

function formatInterval(days: number | null, km: number | null): string {
  const parts: string[] = []

  if (days) parts.push(`${days} day${days === 1 ? '' : 's'}`)
  if (km) parts.push(`${km.toLocaleString()} km`)

  return parts.length > 0 ? parts.join(' / ') : 'Not configured'
}

function formatDate(value: string | null): string {
  if (!value) return '—'

  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
}

function formatNextDue(schedule: MaintenanceSchedule): string {
  const parts: string[] = []

  if (schedule.next_due_at) {
    parts.push(formatDate(schedule.next_due_at))
  }

  if (schedule.next_due_km !== null) {
    parts.push(`${schedule.next_due_km.toLocaleString()} km`)
  }

  return parts.length > 0 ? parts.join(' · ') : 'Not scheduled'
}

async function loadSupportData() {
  try {
    const data = await getResource<MaintenanceScheduleSupportData>('/maintenance-schedules/support-data')
    vehicles.value = data.vehicles
  } catch {
    vehicles.value = []
  }
}

async function loadSummaries() {
  try {
    const [upcoming, overdue] = await Promise.all([
      getResource<MaintenanceSchedule[]>('/maintenance-schedules/upcoming'),
      getResource<MaintenanceSchedule[]>('/maintenance-schedules/overdue'),
    ])

    upcomingSchedules.value = upcoming
    overdueSchedules.value = overdue
  } catch {
    upcomingSchedules.value = []
    overdueSchedules.value = []
  }
}

async function loadSchedules(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<MaintenanceSchedule>('/maintenance-schedules', {
      page,
      search: search.value || undefined,
      filter: {
        status: statusFilter.value,
        schedule_type: typeFilter.value,
        vehicle_id: vehicleFilter.value,
      },
    })

    schedules.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function removeSchedule(id: number) {
  const target = schedules.value.find((item) => item.id === id)

  if (!target || !globalThis.confirm(`Delete maintenance schedule ${target.title}?`)) {
    return
  }

  try {
    await destroyResource(`/maintenance-schedules/${id}`)
    await Promise.all([loadSchedules(meta.value.current_page), loadSummaries()])
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

onMounted(async () => {
  await Promise.all([loadSupportData(), loadSummaries(), loadSchedules()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Maintenance"
      title="Maintenance Schedules"
      description="Preventive and recurring maintenance plans define when assets are due for service by time, distance, or regulatory cadence."
    >
      <template #actions>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'maintenance-schedules.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Create schedule
        </RouterLink>
      </template>
    </PageHeader>

    <div class="grid gap-4 md:grid-cols-3">
      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/70 p-5 shadow-sm shadow-slate-200/60 dark:shadow-black/20">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Tracked schedules</p>
        <p class="mt-3 text-3xl font-semibold text-slate-950 dark:text-slate-100">{{ meta.total }}</p>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Tenant-wide maintenance plans currently available in the registry.</p>
      </div>
      <div class="rounded-2xl border border-amber-200 dark:border-amber-900/60 bg-amber-50/70 dark:bg-amber-950/40 p-5 shadow-sm shadow-amber-100/60 dark:shadow-amber-900/20">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-200">Due soon</p>
        <p class="mt-3 text-3xl font-semibold text-amber-950 dark:text-amber-100">{{ upcomingSchedules.length }}</p>
        <p class="mt-2 text-sm text-amber-900/80">Schedules approaching their configured date or kilometre reminder threshold.</p>
      </div>
      <div class="rounded-2xl border border-rose-200 dark:border-rose-900/60 bg-rose-50/70 dark:bg-rose-950/40 p-5 shadow-sm shadow-rose-100/60 dark:shadow-rose-900/20">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700 dark:text-rose-200">Overdue</p>
        <p class="mt-3 text-3xl font-semibold text-rose-950 dark:text-rose-100">{{ overdueSchedules.length }}</p>
        <p class="mt-2 text-sm text-rose-900/80">Schedules that require action before maintenance debt accumulates further.</p>
      </div>
    </div>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load maintenance schedules"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadSchedules(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search schedule title or vehicle"
          class="min-w-[240px] flex-1 rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option v-for="option in maintenanceScheduleStatusOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="typeFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All schedule types</option>
          <option v-for="option in maintenanceScheduleTypeOptions" :key="option.value" :value="option.value">
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
      empty-title="No maintenance schedules configured"
      empty-description="Create the first recurring schedule to establish preventive maintenance discipline."
    >
      <template #cell-due_status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template #cell-status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template v-if="canManage" #cell-actions="{ row }">
        <div class="flex items-center gap-2">
          <RouterLink
            v-if="auth.hasPermission('maintenance.update')"
            :to="{ name: 'maintenance-schedules.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
          >
            Open
          </RouterLink>
          <button
            v-if="auth.hasPermission('maintenance.delete')"
            type="button"
            class="rounded-xl border border-rose-300 dark:border-rose-800/60 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:text-rose-200 transition hover:bg-rose-50 dark:hover:bg-rose-950/40"
            @click="removeSchedule(Number(row.id))"
          >
            Delete
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadSchedules(meta.current_page - 1)"
      @next="loadSchedules(meta.current_page + 1)"
    />
  </div>
</template>
