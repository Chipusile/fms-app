<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { workOrderPriorityOptions, workOrderStatusOptions } from '@/lib/fleet-options'
import { destroyResource, getResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type { ApiError, PaginationMeta, ReferenceOption, WorkOrder, WorkOrderSupportData } from '@/types'

const auth = useAuthStore()
const workOrders = ref<WorkOrder[]>([])
const vehicles = ref<ReferenceOption[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const priorityFilter = ref('')
const vehicleFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const canCreate = computed(() => auth.hasPermission('maintenance.create'))
const canManage = computed(() => auth.hasAnyPermission(['maintenance.update', 'maintenance.delete']))

const columns = computed(() => (
  canManage.value
    ? [
        { key: 'work_order_number', label: 'Work order' },
        { key: 'title', label: 'Title' },
        { key: 'vehicle', label: 'Vehicle' },
        { key: 'priority', label: 'Priority' },
        { key: 'due_date', label: 'Due date' },
        { key: 'status', label: 'Status' },
        { key: 'assignee', label: 'Assignee' },
        { key: 'actions', label: 'Actions' },
      ]
    : [
        { key: 'work_order_number', label: 'Work order' },
        { key: 'title', label: 'Title' },
        { key: 'vehicle', label: 'Vehicle' },
        { key: 'priority', label: 'Priority' },
        { key: 'due_date', label: 'Due date' },
        { key: 'status', label: 'Status' },
        { key: 'assignee', label: 'Assignee' },
      ]
))

const rows = computed(() => workOrders.value.map((workOrder) => ({
  id: workOrder.id,
  work_order_number: workOrder.work_order_number,
  title: workOrder.title,
  vehicle: workOrder.vehicle?.registration_number ?? 'Unknown vehicle',
  priority: workOrder.priority,
  due_date: formatDate(workOrder.due_date),
  status: workOrder.status,
  assignee: workOrder.assignee?.name ?? 'Unassigned',
})))

const statusCounts = computed(() => ({
  open: workOrders.value.filter((item) => item.status === 'open').length,
  in_progress: workOrders.value.filter((item) => item.status === 'in_progress').length,
  completed: workOrders.value.filter((item) => item.status === 'completed').length,
}))

function formatDate(value: string | null): string {
  if (!value) return '—'

  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
}

async function loadSupportData() {
  try {
    const data = await getResource<WorkOrderSupportData>('/work-orders/support-data')
    vehicles.value = data.vehicles
  } catch {
    vehicles.value = []
  }
}

async function loadWorkOrders(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<WorkOrder>('/work-orders', {
      page,
      search: search.value || undefined,
      filter: {
        status: statusFilter.value,
        priority: priorityFilter.value,
        vehicle_id: vehicleFilter.value,
      },
    })

    workOrders.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function removeWorkOrder(id: number) {
  const target = workOrders.value.find((item) => item.id === id)

  if (!target || !globalThis.confirm(`Delete work order ${target.work_order_number}?`)) {
    return
  }

  try {
    await destroyResource(`/work-orders/${id}`)
    await loadWorkOrders(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

onMounted(async () => {
  await Promise.all([loadSupportData(), loadWorkOrders()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Maintenance"
      title="Work Orders"
      description="Track planned and corrective maintenance execution from open request through completion and cost capture."
    >
      <template #actions>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'work-orders.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Create work order
        </RouterLink>
      </template>
    </PageHeader>

    <div class="grid gap-4 md:grid-cols-3">
      <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm shadow-amber-100/60">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Open</p>
        <p class="mt-3 text-3xl font-semibold text-amber-950">{{ statusCounts.open }}</p>
        <p class="mt-2 text-sm text-amber-900/80">New work orders waiting for assignment or start.</p>
      </div>
      <div class="rounded-2xl border border-sky-200 bg-sky-50/70 p-5 shadow-sm shadow-sky-100/60">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">In progress</p>
        <p class="mt-3 text-3xl font-semibold text-sky-950">{{ statusCounts.in_progress }}</p>
        <p class="mt-2 text-sm text-sky-900/80">Jobs currently under maintenance execution.</p>
      </div>
      <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 shadow-sm shadow-emerald-100/60">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Completed on page</p>
        <p class="mt-3 text-3xl font-semibold text-emerald-950">{{ statusCounts.completed }}</p>
        <p class="mt-2 text-sm text-emerald-900/80">Closed-out work orders visible in the current result set.</p>
      </div>
    </div>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load work orders"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadWorkOrders(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search work order number, title, or vehicle"
          class="min-w-[240px] flex-1 rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option v-for="option in workOrderStatusOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="priorityFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All priorities</option>
          <option v-for="option in workOrderPriorityOptions" :key="option.value" :value="option.value">
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
      empty-title="No work orders available"
      empty-description="Create the first work order to track maintenance execution and completion history."
    >
      <template #cell-priority="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template #cell-status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template v-if="canManage" #cell-actions="{ row }">
        <div class="flex items-center gap-2">
          <RouterLink
            :to="{ name: 'work-orders.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
          >
            Open
          </RouterLink>
          <button
            v-if="auth.hasPermission('maintenance.delete') && row.status !== 'completed'"
            type="button"
            class="rounded-xl border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
            @click="removeWorkOrder(Number(row.id))"
          >
            Delete
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadWorkOrders(meta.current_page - 1)"
      @next="loadWorkOrders(meta.current_page + 1)"
    />
  </div>
</template>
