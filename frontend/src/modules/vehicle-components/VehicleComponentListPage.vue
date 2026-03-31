<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import {
  vehicleComponentStatusOptions,
  vehicleComponentTypeOptions,
} from '@/lib/fleet-options'
import { destroyResource, getResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  PaginationMeta,
  VehicleComponent,
  VehicleComponentSupportData,
  ReferenceOption,
} from '@/types'

const auth = useAuthStore()
const components = ref<VehicleComponent[]>([])
const vehicles = ref<ReferenceOption[]>([])
const dueSoonComponents = ref<VehicleComponent[]>([])
const overdueComponents = ref<VehicleComponent[]>([])
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
        { key: 'component_number', label: 'Component' },
        { key: 'descriptor', label: 'Description' },
        { key: 'vehicle', label: 'Vehicle' },
        { key: 'position_code', label: 'Position' },
        { key: 'next_replacement', label: 'Next replacement' },
        { key: 'due_status', label: 'Due status' },
        { key: 'status', label: 'Lifecycle' },
        { key: 'actions', label: 'Actions' },
      ]
    : [
        { key: 'component_number', label: 'Component' },
        { key: 'descriptor', label: 'Description' },
        { key: 'vehicle', label: 'Vehicle' },
        { key: 'position_code', label: 'Position' },
        { key: 'next_replacement', label: 'Next replacement' },
        { key: 'due_status', label: 'Due status' },
        { key: 'status', label: 'Lifecycle' },
      ]
))

const rows = computed(() => components.value.map((component) => ({
  id: component.id,
  component_number: component.component_number,
  descriptor: buildDescriptor(component),
  vehicle: component.vehicle?.registration_number ?? 'Unknown vehicle',
  position_code: component.position_code ?? '—',
  next_replacement: formatNextReplacement(component),
  due_status: component.due_status,
  status: component.status,
})))

function buildDescriptor(component: VehicleComponent): string {
  const descriptor = [component.brand, component.model].filter(Boolean).join(' ').trim()

  if (descriptor) {
    return `${descriptor} · ${component.component_type.replaceAll('_', ' ')}`
  }

  return component.component_type.replaceAll('_', ' ')
}

function formatDate(value: string | null): string {
  if (!value) return '—'

  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
}

function formatNextReplacement(component: VehicleComponent): string {
  const parts: string[] = []

  if (component.next_replacement_at) {
    parts.push(formatDate(component.next_replacement_at))
  }

  if (component.next_replacement_km !== null) {
    parts.push(`${component.next_replacement_km.toLocaleString()} km`)
  }

  return parts.length > 0 ? parts.join(' · ') : 'Not scheduled'
}

async function loadSupportData() {
  try {
    const data = await getResource<VehicleComponentSupportData>('/vehicle-components/support-data')
    vehicles.value = data.vehicles
  } catch {
    vehicles.value = []
  }
}

async function loadSummaries() {
  try {
    const [dueSoon, overdue] = await Promise.all([
      getResource<VehicleComponent[]>('/vehicle-components/due-soon'),
      getResource<VehicleComponent[]>('/vehicle-components/overdue'),
    ])

    dueSoonComponents.value = dueSoon
    overdueComponents.value = overdue
  } catch {
    dueSoonComponents.value = []
    overdueComponents.value = []
  }
}

async function loadComponents(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<VehicleComponent>('/vehicle-components', {
      page,
      search: search.value || undefined,
      filter: {
        status: statusFilter.value,
        component_type: typeFilter.value,
        vehicle_id: vehicleFilter.value,
      },
    })

    components.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function removeComponent(id: number) {
  const target = components.value.find((item) => item.id === id)

  if (!target || !globalThis.confirm(`Delete component ${target.component_number}?`)) {
    return
  }

  try {
    await destroyResource(`/vehicle-components/${id}`)
    await Promise.all([loadComponents(meta.value.current_page), loadSummaries()])
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

onMounted(async () => {
  await Promise.all([loadSupportData(), loadSummaries(), loadComponents()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Maintenance"
      title="Vehicle Components"
      description="Track lifecycle-critical parts such as tyres, batteries, and trackers with replacement thresholds and tenant-configurable reminders."
    >
      <template #actions>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'vehicle-components.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Add component
        </RouterLink>
      </template>
    </PageHeader>

    <div class="grid gap-4 md:grid-cols-3">
      <div class="rounded-2xl border border-slate-200 bg-white/90 p-5 shadow-sm shadow-slate-200/60">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Tracked components</p>
        <p class="mt-3 text-3xl font-semibold text-slate-950">{{ meta.total }}</p>
        <p class="mt-2 text-sm text-slate-600">Lifecycle-managed components currently visible in the registry.</p>
      </div>
      <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm shadow-amber-100/60">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Due soon</p>
        <p class="mt-3 text-3xl font-semibold text-amber-950">{{ dueSoonComponents.length }}</p>
        <p class="mt-2 text-sm text-amber-900/80">Components approaching replacement date or kilometre thresholds.</p>
      </div>
      <div class="rounded-2xl border border-rose-200 bg-rose-50/70 p-5 shadow-sm shadow-rose-100/60">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">Due replacement</p>
        <p class="mt-3 text-3xl font-semibold text-rose-950">{{ overdueComponents.length }}</p>
        <p class="mt-2 text-sm text-rose-900/80">Components that have crossed configured lifecycle limits and need action.</p>
      </div>
    </div>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load vehicle components"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadComponents(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search component number, serial, brand, or vehicle"
          class="min-w-[240px] flex-1 rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All lifecycle states</option>
          <option v-for="option in vehicleComponentStatusOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="typeFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All component types</option>
          <option v-for="option in vehicleComponentTypeOptions" :key="option.value" :value="option.value">
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
      empty-title="No vehicle components tracked"
      empty-description="Add tyres, batteries, or other components to enable replacement planning and reminders."
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
            :to="{ name: 'vehicle-components.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
          >
            Open
          </RouterLink>
          <button
            v-if="auth.hasPermission('maintenance.delete') && !['retired', 'failed'].includes(String(row.status))"
            type="button"
            class="rounded-xl border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
            @click="removeComponent(Number(row.id))"
          >
            Delete
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadComponents(meta.current_page - 1)"
      @next="loadComponents(meta.current_page + 1)"
    />
  </div>
</template>
