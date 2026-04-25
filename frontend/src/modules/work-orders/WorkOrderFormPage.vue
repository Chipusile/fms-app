<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { workOrderPriorityOptions, workOrderTypeOptions } from '@/lib/fleet-options'
import { createResource, getResource, updateResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  CompleteWorkOrderPayload,
  ReferenceOption,
  WorkOrder,
  WorkOrderPayload,
  WorkOrderSupportData,
  WorkOrderSupportScheduleOption,
  WorkOrderSupportVehicleOption,
} from '@/types'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const isEditMode = computed(() => Boolean(route.params.id))
const canEdit = computed(() => auth.hasPermission('maintenance.update'))
const canCreate = computed(() => auth.hasPermission('maintenance.create'))
const canDelete = computed(() => auth.hasPermission('maintenance.delete'))
const loading = ref(false)
const submitting = ref(false)
const actionLoading = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const vehicles = ref<WorkOrderSupportVehicleOption[]>([])
const schedules = ref<WorkOrderSupportScheduleOption[]>([])
const serviceProviders = ref<ReferenceOption[]>([])
const assignees = ref<ReferenceOption[]>([])
const workOrder = ref<WorkOrder | null>(null)

const completion = ref<CompleteWorkOrderPayload>({
  completed_at: null,
  odometer_reading: null,
  downtime_hours: null,
  labor_cost: null,
  parts_cost: null,
  actual_cost: null,
  resolution_notes: '',
})
const cancelNotes = ref('')

const form = ref<WorkOrderPayload>({
  maintenance_schedule_id: null,
  vehicle_id: 0,
  service_provider_id: null,
  assigned_to: null,
  title: '',
  maintenance_type: 'preventive',
  priority: 'medium',
  due_date: null,
  estimated_cost: null,
  notes: '',
})

const selectedVehicle = computed(() => vehicles.value.find((vehicle) => vehicle.id === form.value.vehicle_id) ?? null)
const filteredSchedules = computed(() => {
  if (!form.value.vehicle_id) return schedules.value
  return schedules.value.filter((schedule) => schedule.vehicle_id === form.value.vehicle_id)
})
const canStart = computed(() => workOrder.value?.status === 'open' && canEdit.value)
const canComplete = computed(() => !!workOrder.value && ['open', 'in_progress'].includes(workOrder.value.status) && canEdit.value)
const canCancel = computed(() => !!workOrder.value && ['open', 'in_progress'].includes(workOrder.value.status) && canEdit.value)

function toNullableNumber(value: number | null | undefined): number | null {
  return value === null || value === undefined || Number.isNaN(Number(value)) ? null : Number(value)
}

function toDateTimeLocal(value: string | null): string | null {
  return value ? value.slice(0, 16) : null
}

function buildPayload(): WorkOrderPayload {
  return {
    maintenance_schedule_id: form.value.maintenance_schedule_id ? Number(form.value.maintenance_schedule_id) : null,
    vehicle_id: Number(form.value.vehicle_id),
    service_provider_id: form.value.service_provider_id ? Number(form.value.service_provider_id) : null,
    assigned_to: form.value.assigned_to ? Number(form.value.assigned_to) : null,
    title: form.value.title,
    maintenance_type: form.value.maintenance_type,
    priority: form.value.priority,
    due_date: form.value.due_date || null,
    estimated_cost: toNullableNumber(form.value.estimated_cost),
    notes: form.value.notes || null,
  }
}

function buildCompletionPayload(): CompleteWorkOrderPayload {
  return {
    completed_at: completion.value.completed_at || null,
    odometer_reading: toNullableNumber(completion.value.odometer_reading),
    downtime_hours: toNullableNumber(completion.value.downtime_hours),
    labor_cost: toNullableNumber(completion.value.labor_cost),
    parts_cost: toNullableNumber(completion.value.parts_cost),
    actual_cost: toNullableNumber(completion.value.actual_cost),
    resolution_notes: completion.value.resolution_notes || null,
  }
}

async function loadSupportData() {
  const data = await getResource<WorkOrderSupportData>('/work-orders/support-data')
  vehicles.value = data.vehicles
  schedules.value = data.schedules
  serviceProviders.value = data.service_providers
  assignees.value = data.assignees

  if (!isEditMode.value && !form.value.vehicle_id && vehicles.value[0]) {
    form.value.vehicle_id = vehicles.value[0].id
  }
}

async function loadWorkOrder() {
  if (!isEditMode.value) return

  const record = await getResource<WorkOrder>(`/work-orders/${route.params.id}`)
  workOrder.value = record
  form.value = {
    maintenance_schedule_id: record.maintenance_schedule_id,
    vehicle_id: record.vehicle_id,
    service_provider_id: record.service_provider_id,
    assigned_to: record.assigned_to,
    title: record.title,
    maintenance_type: record.maintenance_type,
    priority: record.priority,
    due_date: record.due_date,
    estimated_cost: record.estimated_cost !== null ? Number(record.estimated_cost) : null,
    notes: record.notes ?? '',
  }
  completion.value = {
    completed_at: toDateTimeLocal(record.completed_at),
    odometer_reading: record.odometer_reading,
    downtime_hours: toNullableNumber((record.metadata as { downtime_hours?: number | null } | null)?.downtime_hours ?? null),
    labor_cost: toNullableNumber(record.maintenance_record ? Number(record.maintenance_record.labor_cost ?? 0) : null),
    parts_cost: toNullableNumber(record.maintenance_record ? Number(record.maintenance_record.parts_cost ?? 0) : null),
    actual_cost: toNullableNumber(record.actual_cost !== null ? Number(record.actual_cost) : null),
    resolution_notes: record.resolution_notes ?? '',
  }
  cancelNotes.value = record.resolution_notes ?? ''
}

async function refreshWorkOrder() {
  if (!isEditMode.value) return
  await loadWorkOrder()
}

async function submit() {
  if (isEditMode.value && !canEdit.value) return
  if (!isEditMode.value && !canCreate.value) return

  submitting.value = true
  errorMessage.value = null
  successMessage.value = null
  fieldErrors.value = {}

  try {
    const payload = buildPayload()

    if (isEditMode.value) {
      workOrder.value = await updateResource<WorkOrder, WorkOrderPayload>(`/work-orders/${route.params.id}`, payload)
      successMessage.value = 'Work order updated successfully.'
      await refreshWorkOrder()
    } else {
      await createResource<WorkOrder, WorkOrderPayload>('/work-orders', payload)
      await router.push({ name: 'work-orders' })
      return
    }
  } catch (error) {
    const apiError = error as ApiError
    errorMessage.value = apiError.message
    fieldErrors.value = apiError.errors ?? {}
  } finally {
    submitting.value = false
  }
}

async function runAction(action: 'start' | 'complete' | 'cancel', payload: Record<string, unknown> = {}) {
  if (!workOrder.value) return

  actionLoading.value = true
  errorMessage.value = null
  successMessage.value = null
  fieldErrors.value = {}

  try {
    const response = await api.put(`/work-orders/${workOrder.value.id}/${action}`, payload)
    workOrder.value = response.data.data as WorkOrder
    successMessage.value = response.data.message
    await refreshWorkOrder()
  } catch (error) {
    const apiError = error as ApiError
    errorMessage.value = apiError.message
    fieldErrors.value = apiError.errors ?? {}
  } finally {
    actionLoading.value = false
  }
}

async function removeWorkOrder() {
  if (!workOrder.value || !canDelete.value) return

  if (!globalThis.confirm(`Delete work order ${workOrder.value.work_order_number}?`)) {
    return
  }

  try {
    await api.delete(`/work-orders/${workOrder.value.id}`)
    await router.push({ name: 'work-orders' })
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

function errorsFor(field: string): string[] | undefined {
  return fieldErrors.value[field]
}

watch(() => form.value.maintenance_schedule_id, (scheduleId) => {
  if (!scheduleId || isEditMode.value) return

  const selectedSchedule = schedules.value.find((item) => item.id === scheduleId)

  if (selectedSchedule) {
    form.value.vehicle_id = selectedSchedule.vehicle_id
  }
})

watch(() => form.value.vehicle_id, (vehicleId) => {
  if (!vehicleId || isEditMode.value) return

  if (!filteredSchedules.value.some((schedule) => schedule.id === form.value.maintenance_schedule_id)) {
    form.value.maintenance_schedule_id = null
  }
})

onMounted(async () => {
  loading.value = true

  try {
    await loadSupportData()
    await loadWorkOrder()
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Maintenance"
      :title="isEditMode ? 'Manage work order' : 'Create work order'"
      description="Work orders coordinate execution, assignee accountability, cost capture, and maintenance history creation."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'work-orders' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
        >
          Back to work orders
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="successMessage"
      title="Work order updated"
      :description="successMessage"
      tone="success"
    />

    <InlineAlert
      v-if="errorMessage"
      title="Unable to process work order"
      :description="errorMessage"
      tone="danger"
    />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.95fr]" @submit.prevent="submit">
      <SectionCard title="Work order details" description="Define the job scope, linked schedule, responsible party, and commercial due date.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Linked schedule</span>
            <select v-model="form.maintenance_schedule_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
              <option :value="null">Standalone work order</option>
              <option v-for="scheduleOption in filteredSchedules" :key="scheduleOption.id" :value="scheduleOption.id">
                {{ scheduleOption.label }}{{ scheduleOption.secondary ? ` · ${scheduleOption.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('maintenance_schedule_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Vehicle</span>
            <select v-model="form.vehicle_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
              <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                {{ vehicle.label }}{{ vehicle.secondary ? ` · ${vehicle.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('vehicle_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Service provider</span>
            <select v-model="form.service_provider_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
              <option :value="null">Unassigned provider</option>
              <option v-for="provider in serviceProviders" :key="provider.id" :value="provider.id">
                {{ provider.label }}{{ provider.secondary ? ` · ${provider.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('service_provider_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Assigned to</span>
            <select v-model="form.assigned_to" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
              <option :value="null">No assignee yet</option>
              <option v-for="assignee in assignees" :key="assignee.id" :value="assignee.id">
                {{ assignee.label }}{{ assignee.secondary ? ` · ${assignee.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('assigned_to')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Title</span>
            <input v-model="form.title" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('title')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Maintenance type</span>
            <select v-model="form.maintenance_type" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
              <option v-for="option in workOrderTypeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('maintenance_type')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Priority</span>
            <select v-model="form.priority" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
              <option v-for="option in workOrderPriorityOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('priority')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Due date</span>
            <input v-model="form.due_date" type="date" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('due_date')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Estimated cost</span>
            <input v-model.number="form.estimated_cost" type="number" min="0" step="0.01" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('estimated_cost')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Notes</span>
            <textarea v-model="form.notes" class="min-h-28 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)" />
            <FieldError :errors="errorsFor('notes')" />
          </label>
        </div>
      </SectionCard>

      <div class="space-y-6">
        <SectionCard title="Workflow status" description="Start, complete, or cancel the job while preserving the resulting maintenance history.">
          <div class="space-y-4 text-sm text-slate-700 dark:text-slate-200">
            <div v-if="workOrder" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Current work order</p>
                  <p class="mt-1 text-base font-semibold text-slate-900 dark:text-slate-100">{{ workOrder.work_order_number }}</p>
                </div>
                <StatusBadge :value="workOrder.status" />
              </div>
              <div class="mt-3 flex flex-wrap gap-2">
                <StatusBadge :value="workOrder.priority" />
                <span class="rounded-full border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:text-slate-400">
                  {{ selectedVehicle?.label ?? workOrder.vehicle?.registration_number ?? 'Vehicle' }}
                </span>
              </div>
            </div>
            <p v-else class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm leading-6 text-slate-600 dark:text-slate-400">
              Save the work order first to expose execution and closure actions.
            </p>

            <div class="grid gap-4 md:grid-cols-2">
              <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
                <span class="font-medium">Completed at</span>
                <input v-model="completion.completed_at" type="datetime-local" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading || !canEdit">
                <FieldError :errors="errorsFor('completed_at')" />
              </label>
              <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
                <span class="font-medium">Completion odometer</span>
                <input v-model.number="completion.odometer_reading" type="number" min="0" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading || !canEdit">
                <FieldError :errors="errorsFor('odometer_reading')" />
              </label>
              <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
                <span class="font-medium">Downtime hours</span>
                <input v-model.number="completion.downtime_hours" type="number" min="0" step="0.1" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading || !canEdit">
                <FieldError :errors="errorsFor('downtime_hours')" />
              </label>
              <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
                <span class="font-medium">Actual cost</span>
                <input v-model.number="completion.actual_cost" type="number" min="0" step="0.01" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading || !canEdit">
                <FieldError :errors="errorsFor('actual_cost')" />
              </label>
              <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
                <span class="font-medium">Labour cost</span>
                <input v-model.number="completion.labor_cost" type="number" min="0" step="0.01" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading || !canEdit">
                <FieldError :errors="errorsFor('labor_cost')" />
              </label>
              <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
                <span class="font-medium">Parts cost</span>
                <input v-model.number="completion.parts_cost" type="number" min="0" step="0.01" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading || !canEdit">
                <FieldError :errors="errorsFor('parts_cost')" />
              </label>
            </div>

            <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
              <span class="font-medium">Resolution notes</span>
              <textarea v-model="completion.resolution_notes" class="min-h-24 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading || !canEdit" />
              <FieldError :errors="errorsFor('resolution_notes')" />
            </label>

            <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
              <span class="font-medium">Cancellation notes</span>
              <textarea v-model="cancelNotes" class="min-h-20 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading || !canEdit" />
            </label>

            <div class="flex flex-col gap-3">
              <div class="flex flex-col gap-3 sm:flex-row">
                <button
                  v-if="canStart"
                  type="button"
                  class="rounded-2xl border border-sky-300 dark:border-sky-800/60 px-4 py-3 text-sm font-semibold text-sky-700 dark:text-sky-200 transition hover:bg-sky-50 dark:hover:bg-sky-950/40 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="actionLoading"
                  @click="runAction('start')"
                >
                  {{ actionLoading ? 'Working...' : 'Start work order' }}
                </button>
                <button
                  v-if="canComplete"
                  type="button"
                  class="rounded-2xl border border-emerald-300 dark:border-emerald-800/60 px-4 py-3 text-sm font-semibold text-emerald-700 dark:text-emerald-200 transition hover:bg-emerald-50 dark:hover:bg-emerald-950/40 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="actionLoading"
                  @click="runAction('complete', buildCompletionPayload() as Record<string, unknown>)"
                >
                  {{ actionLoading ? 'Working...' : 'Complete work order' }}
                </button>
                <button
                  v-if="canCancel"
                  type="button"
                  class="rounded-2xl border border-rose-300 dark:border-rose-800/60 px-4 py-3 text-sm font-semibold text-rose-700 dark:text-rose-200 transition hover:bg-rose-50 dark:hover:bg-rose-950/40 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="actionLoading"
                  @click="runAction('cancel', { resolution_notes: cancelNotes || null })"
                >
                  {{ actionLoading ? 'Working...' : 'Cancel work order' }}
                </button>
              </div>

              <div class="flex flex-col gap-3 sm:flex-row">
                <RouterLink :to="{ name: 'work-orders' }" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50 sm:w-auto">
                  Cancel
                </RouterLink>
                <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading || submitting || (isEditMode ? !canEdit : !canCreate)">
                  {{ submitting ? 'Saving...' : isEditMode ? 'Save work order' : 'Create work order' }}
                </button>
                <button
                  v-if="isEditMode && canDelete && workOrder?.status !== 'completed'"
                  type="button"
                  class="w-full rounded-2xl border border-rose-300 dark:border-rose-800/60 px-4 py-3 text-sm font-semibold text-rose-700 dark:text-rose-200 transition hover:bg-rose-50 dark:hover:bg-rose-950/40 sm:w-auto"
                  @click="removeWorkOrder"
                >
                  Delete
                </button>
              </div>
            </div>
          </div>
        </SectionCard>

        <SectionCard v-if="workOrder?.maintenance_record" title="Generated maintenance record" description="Completing the work order creates an immutable maintenance history entry.">
          <div class="space-y-3 text-sm text-slate-700 dark:text-slate-200">
            <p><span class="font-medium text-slate-900 dark:text-slate-100">Summary:</span> {{ workOrder.maintenance_record.summary }}</p>
            <p><span class="font-medium text-slate-900 dark:text-slate-100">Completed:</span> {{ workOrder.maintenance_record.completed_at ? workOrder.maintenance_record.completed_at.slice(0, 10) : '—' }}</p>
            <p><span class="font-medium text-slate-900 dark:text-slate-100">Total cost:</span> {{ workOrder.maintenance_record.total_cost ?? '—' }}</p>
            <p><span class="font-medium text-slate-900 dark:text-slate-100">Recorded odometer:</span> {{ workOrder.maintenance_record.odometer_reading ?? '—' }}</p>
          </div>
        </SectionCard>
      </div>
    </form>
  </div>
</template>
