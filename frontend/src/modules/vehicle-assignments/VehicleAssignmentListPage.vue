<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { vehicleAssignmentStatusOptions } from '@/lib/fleet-options'
import { destroyResource, getResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  PaginationMeta,
  ReferenceOption,
  VehicleAssignment,
  VehicleAssignmentSupportData,
} from '@/types'

const auth = useAuthStore()
const baseColumns = [
  { key: 'vehicle', label: 'Vehicle' },
  { key: 'assignee', label: 'Assigned to' },
  { key: 'department', label: 'Department' },
  { key: 'period', label: 'Period' },
  { key: 'status', label: 'Status' },
]

const assignments = ref<VehicleAssignment[]>([])
const vehicles = ref<ReferenceOption[]>([])
const drivers = ref<ReferenceOption[]>([])
const departments = ref<ReferenceOption[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const vehicleFilter = ref('')
const driverFilter = ref('')
const departmentFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const canCreate = computed(() => auth.hasPermission('vehicles.assign'))
const canManage = computed(() => auth.hasPermission('vehicles.assign'))
const columns = computed(() => (
  canManage.value ? [...baseColumns, { key: 'actions', label: 'Actions' }] : baseColumns
))

const rows = computed(() => assignments.value.map((assignment) => ({
  id: assignment.id,
  vehicle: assignment.vehicle ? `${assignment.vehicle.registration_number} · ${assignment.vehicle.make} ${assignment.vehicle.model}` : 'Unknown vehicle',
  assignee: assignment.driver?.name ?? (assignment.assignment_type === 'pool' ? 'Shared pool' : 'Department allocation'),
  department: assignment.department?.name ?? '—',
  period: `${assignment.assigned_from} → ${assignment.assigned_to ?? 'Open'}`,
  status: assignment.status,
})))

async function loadSupportData() {
  try {
    const data = await getResource<VehicleAssignmentSupportData>('/vehicle-assignments/support-data')
    vehicles.value = data.vehicles
    drivers.value = data.drivers
    departments.value = data.departments
  } catch {
    vehicles.value = []
    drivers.value = []
    departments.value = []
  }
}

async function loadAssignments(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<VehicleAssignment>('/vehicle-assignments', {
      page,
      search: search.value || undefined,
      filter: {
        status: statusFilter.value,
        vehicle_id: vehicleFilter.value,
        driver_id: driverFilter.value,
        department_id: departmentFilter.value,
      },
    })

    assignments.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function removeAssignment(id: number) {
  const target = assignments.value.find((item) => item.id === id)

  if (!target || !globalThis.confirm(`Delete assignment record for ${target.vehicle?.registration_number ?? 'this vehicle'}?`)) {
    return
  }

  try {
    await destroyResource(`/vehicle-assignments/${id}`)
    await loadAssignments(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

onMounted(async () => {
  await Promise.all([loadSupportData(), loadAssignments()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Allocations"
      title="Vehicle assignments"
      description="Track active and historical vehicle ownership by driver, department, or shared pool without losing lifecycle history."
    >
      <template #actions>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'vehicle-assignments.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Create assignment
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load assignments"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadAssignments(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search by vehicle, driver, or department"
          class="min-w-[240px] flex-1 rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select v-model="vehicleFilter" class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500">
          <option value="">All vehicles</option>
          <option v-for="vehicle in vehicles" :key="vehicle.id" :value="String(vehicle.id)">
            {{ vehicle.label }}
          </option>
        </select>
        <select v-model="driverFilter" class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500">
          <option value="">All drivers</option>
          <option v-for="driver in drivers" :key="driver.id" :value="String(driver.id)">
            {{ driver.label }}
          </option>
        </select>
        <select v-model="departmentFilter" class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500">
          <option value="">All departments</option>
          <option v-for="department in departments" :key="department.id" :value="String(department.id)">
            {{ department.label }}
          </option>
        </select>
        <select v-model="statusFilter" class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500">
          <option value="">All statuses</option>
          <option v-for="option in vehicleAssignmentStatusOptions" :key="option.value" :value="option.value">
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
      empty-title="No assignment records found"
      empty-description="Assignments remain auditable history records, so create the first one before Phase 3 dispatch workflows begin."
    >
      <template #cell-status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template v-if="canManage" #cell-actions="{ row }">
        <div class="flex items-center gap-2">
          <RouterLink
            :to="{ name: 'vehicle-assignments.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
          >
            Edit
          </RouterLink>
          <button
            type="button"
            class="rounded-xl border border-rose-300 dark:border-rose-800/60 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:text-rose-200 transition hover:bg-rose-50 dark:hover:bg-rose-950/40"
            @click="removeAssignment(Number(row.id))"
          >
            Delete
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadAssignments(meta.current_page - 1)"
      @next="loadAssignments(meta.current_page + 1)"
    />
  </div>
</template>
