<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import {
  maintenanceRequestPriorityOptions,
  maintenanceRequestTypeOptions,
} from '@/lib/fleet-options'
import { createResource, getResource, updateResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  ConvertMaintenanceRequestPayload,
  MaintenanceRequest,
  MaintenanceRequestDecisionPayload,
  MaintenanceRequestPayload,
  MaintenanceRequestSupportData,
  MaintenanceRequestSupportScheduleOption,
  MaintenanceRequestSupportVehicleOption,
  ReferenceOption,
} from '@/types'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const isEditMode = computed(() => Boolean(route.params.id))
const canEdit = computed(() => auth.hasPermission('maintenance.update'))
const canCreate = computed(() => auth.hasPermission('maintenance.create'))
const canApprove = computed(() => auth.hasPermission('maintenance.approve'))
const canDelete = computed(() => auth.hasPermission('maintenance.delete'))
const loading = ref(false)
const submitting = ref(false)
const actionLoading = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const vehicles = ref<MaintenanceRequestSupportVehicleOption[]>([])
const schedules = ref<MaintenanceRequestSupportScheduleOption[]>([])
const serviceProviders = ref<ReferenceOption[]>([])
const assignees = ref<ReferenceOption[]>([])
const maintenanceRequest = ref<MaintenanceRequest | null>(null)

const form = ref<MaintenanceRequestPayload>({
  maintenance_schedule_id: null,
  vehicle_id: 0,
  service_provider_id: null,
  title: '',
  request_type: 'corrective',
  priority: 'medium',
  needed_by: null,
  odometer_reading: null,
  description: '',
  review_notes: '',
})

const decision = ref<MaintenanceRequestDecisionPayload>({
  review_notes: '',
})

const convertForm = ref<ConvertMaintenanceRequestPayload>({
  service_provider_id: null,
  assigned_to: null,
  title: '',
  due_date: null,
  estimated_cost: null,
  notes: '',
  review_notes: '',
})

const canEditRecord = computed(() => {
  if (!isEditMode.value) return canCreate.value

  return canEdit.value && maintenanceRequest.value?.status === 'submitted'
})

const canApproveRecord = computed(() => isEditMode.value && canApprove.value && maintenanceRequest.value?.status === 'submitted')
const canCancelRecord = computed(() => isEditMode.value && canEdit.value && ['submitted', 'approved'].includes(maintenanceRequest.value?.status ?? ''))
const canConvertRecord = computed(() => isEditMode.value && canApprove.value && maintenanceRequest.value?.status === 'approved' && !maintenanceRequest.value?.work_order)
const selectedVehicle = computed(() => vehicles.value.find((vehicle) => vehicle.id === form.value.vehicle_id) ?? null)
const filteredSchedules = computed(() => {
  if (!form.value.vehicle_id) return schedules.value

  return schedules.value.filter((schedule) => schedule.vehicle_id === form.value.vehicle_id)
})

function toNullableNumber(value: number | null | undefined): number | null {
  return value === null || value === undefined || Number.isNaN(Number(value)) ? null : Number(value)
}

function buildPayload(): MaintenanceRequestPayload {
  return {
    maintenance_schedule_id: form.value.maintenance_schedule_id ? Number(form.value.maintenance_schedule_id) : null,
    vehicle_id: Number(form.value.vehicle_id),
    service_provider_id: form.value.service_provider_id ? Number(form.value.service_provider_id) : null,
    title: form.value.title,
    request_type: form.value.request_type,
    priority: form.value.priority,
    needed_by: form.value.needed_by || null,
    odometer_reading: toNullableNumber(form.value.odometer_reading),
    description: form.value.description,
    review_notes: form.value.review_notes || null,
  }
}

function buildConvertPayload(): ConvertMaintenanceRequestPayload {
  return {
    service_provider_id: convertForm.value.service_provider_id ? Number(convertForm.value.service_provider_id) : null,
    assigned_to: convertForm.value.assigned_to ? Number(convertForm.value.assigned_to) : null,
    title: convertForm.value.title || null,
    due_date: convertForm.value.due_date || null,
    estimated_cost: toNullableNumber(convertForm.value.estimated_cost),
    notes: convertForm.value.notes || null,
    review_notes: convertForm.value.review_notes || null,
  }
}

async function loadSupportData() {
  const data = await getResource<MaintenanceRequestSupportData>('/maintenance-requests/support-data')
  vehicles.value = data.vehicles
  schedules.value = data.schedules
  serviceProviders.value = data.service_providers
  assignees.value = data.assignees

  if (!isEditMode.value && !form.value.vehicle_id && vehicles.value[0]) {
    form.value.vehicle_id = vehicles.value[0].id
    form.value.odometer_reading = vehicles.value[0].odometer_reading
  }
}

async function loadMaintenanceRequest() {
  if (!isEditMode.value) return

  const record = await getResource<MaintenanceRequest>(`/maintenance-requests/${route.params.id}`)
  maintenanceRequest.value = record
  form.value = {
    maintenance_schedule_id: record.maintenance_schedule_id,
    vehicle_id: record.vehicle_id,
    service_provider_id: record.service_provider_id,
    title: record.title,
    request_type: record.request_type,
    priority: record.priority,
    needed_by: record.needed_by,
    odometer_reading: record.odometer_reading,
    description: record.description,
    review_notes: record.review_notes ?? '',
  }
  decision.value.review_notes = record.review_notes ?? ''
  convertForm.value = {
    service_provider_id: record.service_provider_id,
    assigned_to: null,
    title: record.title,
    due_date: record.needed_by,
    estimated_cost: null,
    notes: '',
    review_notes: record.review_notes ?? '',
  }
}

async function refreshMaintenanceRequest() {
  if (!isEditMode.value) return

  await loadMaintenanceRequest()
}

async function submit() {
  if (isEditMode.value && !canEditRecord.value) return
  if (!isEditMode.value && !canCreate.value) return

  submitting.value = true
  errorMessage.value = null
  successMessage.value = null
  fieldErrors.value = {}

  try {
    const payload = buildPayload()

    if (isEditMode.value) {
      maintenanceRequest.value = await updateResource<MaintenanceRequest, MaintenanceRequestPayload>(`/maintenance-requests/${route.params.id}`, payload)
      successMessage.value = 'Maintenance request updated successfully.'
      await refreshMaintenanceRequest()
    } else {
      await createResource<MaintenanceRequest, MaintenanceRequestPayload>('/maintenance-requests', payload)
      await router.push({ name: 'maintenance-requests' })
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

async function runDecision(action: 'approve' | 'reject' | 'cancel') {
  if (!maintenanceRequest.value) return

  actionLoading.value = true
  errorMessage.value = null
  successMessage.value = null
  fieldErrors.value = {}

  try {
    const response = await api.put(`/maintenance-requests/${maintenanceRequest.value.id}/${action}`, {
      review_notes: decision.value.review_notes || null,
    })

    maintenanceRequest.value = response.data.data as MaintenanceRequest
    successMessage.value = response.data.message
    await refreshMaintenanceRequest()
  } catch (error) {
    const apiError = error as ApiError
    errorMessage.value = apiError.message
    fieldErrors.value = apiError.errors ?? {}
  } finally {
    actionLoading.value = false
  }
}

async function convertToWorkOrder() {
  if (!maintenanceRequest.value) return

  actionLoading.value = true
  errorMessage.value = null
  successMessage.value = null
  fieldErrors.value = {}

  try {
    const response = await api.put(`/maintenance-requests/${maintenanceRequest.value.id}/convert`, buildConvertPayload())
    maintenanceRequest.value = response.data.data as MaintenanceRequest
    successMessage.value = response.data.message
    await refreshMaintenanceRequest()
  } catch (error) {
    const apiError = error as ApiError
    errorMessage.value = apiError.message
    fieldErrors.value = apiError.errors ?? {}
  } finally {
    actionLoading.value = false
  }
}

async function removeMaintenanceRequest() {
  if (!maintenanceRequest.value || !canDelete.value) return

  if (!globalThis.confirm(`Delete maintenance request ${maintenanceRequest.value.request_number}?`)) {
    return
  }

  try {
    await api.delete(`/maintenance-requests/${maintenanceRequest.value.id}`)
    await router.push({ name: 'maintenance-requests' })
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

  const vehicle = vehicles.value.find((item) => item.id === vehicleId)

  if (vehicle) {
    form.value.odometer_reading = vehicle.odometer_reading
  }

  if (!filteredSchedules.value.some((schedule) => schedule.id === form.value.maintenance_schedule_id)) {
    form.value.maintenance_schedule_id = null
  }
})

onMounted(async () => {
  loading.value = true

  try {
    await loadSupportData()
    await loadMaintenanceRequest()
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
      :title="isEditMode ? 'Manage maintenance request' : 'Create maintenance request'"
      description="Maintenance requests capture demand, route approvals, and convert cleanly into work orders without bypassing auditability."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'maintenance-requests' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
        >
          Back to requests
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="successMessage"
      title="Maintenance request updated"
      :description="successMessage"
      tone="success"
    />

    <InlineAlert
      v-if="errorMessage"
      title="Unable to save maintenance request"
      :description="errorMessage"
      tone="danger"
    />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.95fr]" @submit.prevent="submit">
      <SectionCard title="Request details" description="Capture the asset, need, and request context before approval or execution begins.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Vehicle</span>
            <select v-model="form.vehicle_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
              <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                {{ vehicle.label }}{{ vehicle.secondary ? ` · ${vehicle.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('vehicle_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Related schedule</span>
            <select v-model="form.maintenance_schedule_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
              <option :value="null">No linked schedule</option>
              <option v-for="schedule in filteredSchedules" :key="schedule.id" :value="schedule.id">
                {{ schedule.label }}{{ schedule.secondary ? ` · ${schedule.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('maintenance_schedule_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Preferred service provider</span>
            <select v-model="form.service_provider_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
              <option :value="null">Not assigned</option>
              <option v-for="provider in serviceProviders" :key="provider.id" :value="provider.id">
                {{ provider.label }}{{ provider.secondary ? ` · ${provider.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('service_provider_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Needed by</span>
            <input v-model="form.needed_by" type="date" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
            <FieldError :errors="errorsFor('needed_by')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Title</span>
            <input v-model="form.title" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
            <FieldError :errors="errorsFor('title')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Request type</span>
            <select v-model="form.request_type" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
              <option v-for="option in maintenanceRequestTypeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('request_type')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Priority</span>
            <select v-model="form.priority" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
              <option v-for="option in maintenanceRequestPriorityOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('priority')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Observed odometer</span>
            <input v-model.number="form.odometer_reading" type="number" min="0" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
            <FieldError :errors="errorsFor('odometer_reading')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Description</span>
            <textarea v-model="form.description" class="min-h-32 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord" />
            <FieldError :errors="errorsFor('description')" />
          </label>
        </div>
      </SectionCard>

      <div class="space-y-6">
        <SectionCard title="Workflow context" description="Monitor approval status, conversion readiness, and linked execution records.">
          <div class="space-y-4 text-sm text-slate-700 dark:text-slate-200">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3">
              <p class="text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Selected vehicle</p>
              <p class="mt-1 text-base font-semibold text-slate-900 dark:text-slate-100">
                {{ selectedVehicle?.label ?? 'No vehicle selected' }}
              </p>
              <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Current odometer: {{ selectedVehicle ? `${selectedVehicle.odometer_reading.toLocaleString()} km` : '—' }}
              </p>
            </div>

            <div v-if="maintenanceRequest" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Current workflow state</p>
                  <p class="mt-1 text-base font-semibold text-slate-900 dark:text-slate-100">{{ maintenanceRequest.request_number }}</p>
                </div>
                <StatusBadge :value="maintenanceRequest.status" />
              </div>
              <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                Requested by {{ maintenanceRequest.requester?.name ?? 'Unknown user' }} on
                {{ maintenanceRequest.requested_at ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(maintenanceRequest.requested_at)) : '—' }}
              </p>
              <p v-if="maintenanceRequest.reviewer" class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Reviewed by {{ maintenanceRequest.reviewer.name }}
              </p>
              <p v-if="maintenanceRequest.work_order" class="mt-3 text-sm text-slate-700 dark:text-slate-200">
                Linked work order:
                <RouterLink :to="{ name: 'work-orders.edit', params: { id: String(maintenanceRequest.work_order.id) } }" class="font-semibold text-blue-700 dark:text-blue-200 hover:text-blue-900 dark:hover:text-blue-100">
                  {{ maintenanceRequest.work_order.work_order_number }}
                </RouterLink>
              </p>
            </div>
            <p v-else class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm leading-6 text-slate-600 dark:text-slate-400">
              New requests start in a submitted state and can then be approved, rejected, or converted into work orders.
            </p>

            <label v-if="isEditMode" class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
              <span class="font-medium">Review notes</span>
              <textarea v-model="decision.review_notes" class="min-h-24 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading || (!canApproveRecord && !canCancelRecord)" />
              <FieldError :errors="errorsFor('review_notes')" />
            </label>

            <div v-if="canConvertRecord" class="space-y-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-4">
              <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Convert to work order</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Approved requests can be turned into execution work without re-entering maintenance details.</p>
              </div>
              <div class="grid gap-4 md:grid-cols-2">
                <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
                  <span class="font-medium">Execution provider</span>
                  <select v-model="convertForm.service_provider_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading">
                    <option :value="null">Use request provider</option>
                    <option v-for="provider in serviceProviders" :key="provider.id" :value="provider.id">
                      {{ provider.label }}{{ provider.secondary ? ` · ${provider.secondary}` : '' }}
                    </option>
                  </select>
                  <FieldError :errors="errorsFor('service_provider_id')" />
                </label>
                <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
                  <span class="font-medium">Assignee</span>
                  <select v-model="convertForm.assigned_to" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading">
                    <option :value="null">Unassigned</option>
                    <option v-for="assignee in assignees" :key="assignee.id" :value="assignee.id">
                      {{ assignee.label }}{{ assignee.secondary ? ` · ${assignee.secondary}` : '' }}
                    </option>
                  </select>
                  <FieldError :errors="errorsFor('assigned_to')" />
                </label>
                <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
                  <span class="font-medium">Work order title</span>
                  <input v-model="convertForm.title" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading">
                  <FieldError :errors="errorsFor('title')" />
                </label>
                <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
                  <span class="font-medium">Due date</span>
                  <input v-model="convertForm.due_date" type="date" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading">
                  <FieldError :errors="errorsFor('due_date')" />
                </label>
                <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
                  <span class="font-medium">Estimated cost</span>
                  <input v-model.number="convertForm.estimated_cost" type="number" min="0" step="0.01" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading">
                  <FieldError :errors="errorsFor('estimated_cost')" />
                </label>
                <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
                  <span class="font-medium">Execution notes</span>
                  <textarea v-model="convertForm.notes" class="min-h-24 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading" />
                  <FieldError :errors="errorsFor('notes')" />
                </label>
              </div>
            </div>

            <div class="flex flex-col gap-3">
              <div class="flex flex-col gap-3 sm:flex-row">
                <RouterLink :to="{ name: 'maintenance-requests' }" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50 sm:w-auto">
                  Cancel
                </RouterLink>
                <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading || submitting || (isEditMode ? !canEditRecord : !canCreate)">
                  {{ submitting ? 'Saving...' : isEditMode ? 'Save request' : 'Create request' }}
                </button>
              </div>

              <div v-if="isEditMode" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <button
                  v-if="canApproveRecord"
                  type="button"
                  class="rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="actionLoading"
                  @click="runDecision('approve')"
                >
                  Approve request
                </button>
                <button
                  v-if="canApproveRecord"
                  type="button"
                  class="rounded-2xl border border-rose-300 dark:border-rose-800/60 px-4 py-3 text-sm font-semibold text-rose-700 dark:text-rose-200 transition hover:bg-rose-50 dark:hover:bg-rose-950/40 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="actionLoading"
                  @click="runDecision('reject')"
                >
                  Reject request
                </button>
                <button
                  v-if="canCancelRecord"
                  type="button"
                  class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="actionLoading"
                  @click="runDecision('cancel')"
                >
                  Cancel request
                </button>
                <button
                  v-if="canConvertRecord"
                  type="button"
                  class="rounded-2xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="actionLoading"
                  @click="convertToWorkOrder"
                >
                  Convert to work order
                </button>
                <button
                  v-if="canDelete && maintenanceRequest?.status !== 'converted'"
                  type="button"
                  class="rounded-2xl border border-rose-300 dark:border-rose-800/60 px-4 py-3 text-sm font-semibold text-rose-700 dark:text-rose-200 transition hover:bg-rose-50 dark:hover:bg-rose-950/40"
                  @click="removeMaintenanceRequest"
                >
                  Delete request
                </button>
              </div>
            </div>
          </div>
        </SectionCard>
      </div>
    </form>
  </div>
</template>
