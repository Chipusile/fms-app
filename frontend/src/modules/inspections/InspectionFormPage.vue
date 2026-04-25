<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { createResource, getResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  ApiResponse,
  CreateInspectionPayload,
  Inspection,
  InspectionDefectSeverity,
  InspectionResponsePayload,
  InspectionResponseValue,
  InspectionSupportData,
  InspectionTemplate,
  NotificationMeta,
  TripSupportVehicleOption,
} from '@/types'
import { inspectionDefectSeverityOptions } from '@/lib/fleet-options'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const isViewMode = computed(() => Boolean(route.params.id))
const canCreate = computed(() => auth.hasPermission('inspections.create'))
const canClose = computed(() => auth.hasPermission('inspections.update'))
const loading = ref(false)
const submitting = ref(false)
const actionLoading = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const supportData = ref<InspectionSupportData | null>(null)
const inspection = ref<Inspection | null>(null)
const closeNotes = ref('')

const form = ref<CreateInspectionPayload>({
  inspection_template_id: 0,
  vehicle_id: 0,
  driver_id: null,
  trip_id: null,
  performed_at: new Date().toISOString().slice(0, 16),
  odometer_reading: null,
  notes: '',
  responses: [],
})

const templates = computed(() => supportData.value?.templates ?? [])
const vehicles = computed(() => supportData.value?.vehicles ?? [])
const drivers = computed(() => supportData.value?.drivers ?? [])
const trips = computed(() => supportData.value?.trips ?? [])
const selectedTemplate = computed(() => templates.value.find((template) => template.id === form.value.inspection_template_id) ?? null)
const selectedVehicle = computed(() => vehicles.value.find((vehicle) => vehicle.id === form.value.vehicle_id) ?? null)
const filteredTrips = computed(() => trips.value.filter((trip) => !form.value.vehicle_id || trip.vehicle_id === form.value.vehicle_id))
const reviewRequired = computed(() => {
  if (inspection.value) {
    return Boolean(inspection.value.metadata?.review_required)
  }

  return Boolean(selectedTemplate.value?.requires_review_on_critical)
})
const displayResponses = computed(() => {
  if (isViewMode.value) {
    return (inspection.value?.responses ?? []).map((response) => ({
      key: response.id,
      label: response.item_label,
      responseType: 'text',
      required: false,
      responseValue: response.response_value,
      isPass: response.is_pass,
      defectSeverity: response.defect_severity,
      defectSummary: response.defect_summary,
      notes: response.notes,
    }))
  }

  return (selectedTemplate.value?.items ?? []).map((item, index) => {
    const response = form.value.responses[index]

    return {
      key: item.id,
      label: item.title,
      description: item.description,
      responseType: item.response_type,
      required: item.is_required,
      responseValue: response?.response_value ?? null,
      isPass: response?.is_pass ?? null,
      defectSeverity: response?.defect_severity ?? null,
      defectSummary: response?.defect_summary ?? null,
      notes: response?.notes ?? '',
    }
  })
})

function makeEmptyResponse(templateItemId: number): InspectionResponsePayload {
  return {
    template_item_id: templateItemId,
    response_value: null,
    is_pass: null,
    defect_severity: null,
    defect_summary: '',
    notes: '',
  }
}

function toDateTimeLocal(value: string | null): string {
  return value ? value.slice(0, 16) : ''
}

function responseLabel(value: InspectionResponseValue, isPass: boolean | null): string {
  if (typeof isPass === 'boolean') {
    return isPass ? 'Pass' : 'Fail'
  }

  if (typeof value === 'boolean') {
    return value ? 'Yes' : 'No'
  }

  if (value === null || value === '') {
    return '—'
  }

  return String(value)
}

function syncResponses(template: InspectionTemplate | null) {
  if (!template || isViewMode.value) {
    return
  }

  form.value.responses = (template.items ?? []).map((item) => makeEmptyResponse(item.id))
}

function setResponseValue(index: number, value: InspectionResponseValue) {
  const target = form.value.responses[index]

  if (!target) {
    return
  }

  target.response_value = value
}

function setBooleanResponse(index: number, value: string) {
  if (value === '') {
    setResponseValue(index, null)
    return
  }

  setResponseValue(index, value === 'true')
}

function setPassFail(index: number, value: string) {
  const target = form.value.responses[index]

  if (!target) {
    return
  }

  if (value === '') {
    target.is_pass = null
    target.response_value = null
    target.defect_severity = null
    target.defect_summary = ''
    return
  }

  target.is_pass = value === 'pass'
  target.response_value = value

  if (target.is_pass) {
    target.defect_severity = null
    target.defect_summary = ''
  }
}

function setResponseNotes(index: number, value: string) {
  const target = form.value.responses[index]

  if (!target) {
    return
  }

  target.notes = value
}

function setDefectSeverity(index: number, value: string) {
  const target = form.value.responses[index]

  if (!target) {
    return
  }

  target.defect_severity = (value || null) as InspectionDefectSeverity | null
}

function setDefectSummary(index: number, value: string) {
  const target = form.value.responses[index]

  if (!target) {
    return
  }

  target.defect_summary = value
}

async function loadSupportData() {
  const data = await getResource<InspectionSupportData>('/inspections/support-data')
  supportData.value = data

  if (!isViewMode.value) {
    if (!form.value.inspection_template_id && data.templates[0]) {
      form.value.inspection_template_id = data.templates[0].id
    }

    if (!form.value.vehicle_id && data.vehicles[0]) {
      form.value.vehicle_id = data.vehicles[0].id
      form.value.odometer_reading = data.vehicles[0].odometer_reading
    }

    if (!form.value.driver_id && data.drivers[0]) {
      form.value.driver_id = data.drivers[0].id
    }

    syncResponses(data.templates.find((template) => template.id === form.value.inspection_template_id) ?? null)
  }
}

async function loadInspection() {
  if (!isViewMode.value) {
    return
  }

  const record = await getResource<Inspection>(`/inspections/${route.params.id}`)
  inspection.value = record
  closeNotes.value = record.resolution_notes ?? ''
  form.value = {
    inspection_template_id: record.inspection_template_id,
    vehicle_id: record.vehicle_id,
    driver_id: record.driver_id,
    trip_id: record.trip_id,
    performed_at: toDateTimeLocal(record.performed_at),
    odometer_reading: record.odometer_reading,
    notes: record.notes ?? '',
    responses: (record.responses ?? []).map((response) => ({
      template_item_id: response.inspection_template_item_id,
      response_value: response.response_value,
      is_pass: response.is_pass,
      defect_severity: response.defect_severity,
      defect_summary: response.defect_summary ?? '',
      notes: response.notes ?? '',
    })),
  }
}

async function submit() {
  if (!canCreate.value || isViewMode.value) {
    return
  }

  submitting.value = true
  errorMessage.value = null
  successMessage.value = null
  fieldErrors.value = {}

  try {
    const created = await createResource<Inspection, CreateInspectionPayload>('/inspections', form.value)
    successMessage.value = 'Inspection recorded successfully.'
    await router.push({ name: 'inspections.show', params: { id: String(created.id) } })
  } catch (error) {
    const apiError = error as ApiError
    errorMessage.value = apiError.message
    fieldErrors.value = apiError.errors ?? {}
  } finally {
    submitting.value = false
  }
}

async function closeInspection() {
  if (!inspection.value || !canClose.value) {
    return
  }

  actionLoading.value = true
  errorMessage.value = null
  successMessage.value = null
  fieldErrors.value = {}

  try {
    const response = await api.put<ApiResponse<Inspection>>(`/inspections/${inspection.value.id}/close`, {
      resolution_notes: closeNotes.value || null,
    })

    inspection.value = response.data.data
    successMessage.value = response.data.message
    await loadInspection()
  } catch (error) {
    const apiError = error as ApiError
    errorMessage.value = apiError.message
    fieldErrors.value = apiError.errors ?? {}
  } finally {
    actionLoading.value = false
  }
}

function errorsFor(field: string): string[] | undefined {
  return fieldErrors.value[field]
}

watch(selectedTemplate, (template) => {
  syncResponses(template)
})

watch(selectedVehicle, (vehicle) => {
  if (!vehicle || isViewMode.value) {
    return
  }

  form.value.odometer_reading = vehicle.odometer_reading
})

watch(() => form.value.trip_id, (tripId) => {
  if (isViewMode.value) {
    return
  }

  const trip = trips.value.find((item) => item.id === tripId)

  if (!trip) {
    return
  }

  form.value.vehicle_id = trip.vehicle_id
  form.value.driver_id = trip.driver_id
})

onMounted(async () => {
  loading.value = true

  try {
    await loadSupportData()
    await loadInspection()
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
      eyebrow="Operations"
      :title="isViewMode ? (inspection?.inspection_number ?? 'Inspection details') : 'Record inspection'"
      description="Inspections translate checklist execution into auditable operational records, defect visibility, and approval triggers."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'inspections' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
        >
          Back to inspections
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="reviewRequired && !isViewMode"
      title="Critical findings can trigger review"
      description="This template is configured to route critical defects into the approval queue for operational follow-up."
      tone="info"
    />

    <InlineAlert
      v-if="successMessage"
      title="Inspection updated"
      :description="successMessage"
      tone="success"
    />

    <InlineAlert
      v-if="errorMessage"
      title="Unable to process inspection"
      :description="errorMessage"
      tone="danger"
    />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.95fr]" @submit.prevent="submit">
      <SectionCard title="Inspection details" description="Identify the checklist, asset, timing, and operating context for this inspection.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Template</span>
            <select
              v-model="form.inspection_template_id"
              class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || isViewMode"
            >
              <option v-for="template in templates" :key="template.id" :value="template.id">
                {{ template.name }} · {{ template.code }}
              </option>
            </select>
            <FieldError :errors="errorsFor('inspection_template_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Vehicle</span>
            <select
              v-model="form.vehicle_id"
              class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || isViewMode"
            >
              <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                {{ vehicle.label }}{{ vehicle.secondary ? ` · ${vehicle.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('vehicle_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Driver</span>
            <select
              v-model="form.driver_id"
              class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || isViewMode"
            >
              <option :value="null">No driver linked</option>
              <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                {{ driver.label }}{{ driver.secondary ? ` · ${driver.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('driver_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Trip</span>
            <select
              v-model="form.trip_id"
              class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || isViewMode"
            >
              <option :value="null">Not linked to a trip</option>
              <option v-for="trip in filteredTrips" :key="trip.id" :value="trip.id">
                {{ trip.label }}{{ trip.secondary ? ` · ${trip.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('trip_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Performed at</span>
            <input
              v-model="form.performed_at"
              type="datetime-local"
              class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || isViewMode"
            >
            <FieldError :errors="errorsFor('performed_at')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Odometer reading</span>
            <input
              v-model.number="form.odometer_reading"
              type="number"
              min="0"
              class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || isViewMode"
            >
            <FieldError :errors="errorsFor('odometer_reading')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Notes</span>
            <textarea
              v-model="form.notes"
              class="min-h-28 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || isViewMode"
            />
            <FieldError :errors="errorsFor('notes')" />
          </label>
        </div>
      </SectionCard>

      <SectionCard title="Workflow summary" description="Review status, outcome, and governance context without leaving the record.">
        <div class="space-y-4 text-sm text-slate-700 dark:text-slate-200">
          <div
            v-if="inspection"
            class="flex items-center justify-between rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3"
          >
            <div>
              <p class="text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Current status</p>
              <p class="mt-1 text-base font-semibold text-slate-900 dark:text-slate-100">{{ inspection.inspection_number }}</p>
            </div>
            <StatusBadge :value="inspection.status" />
          </div>
          <p
            v-else
            class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-xs leading-5 text-slate-600 dark:text-slate-400"
          >
            Save the inspection first to expose closure and approval history.
          </p>

          <div class="grid gap-3 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3">
              <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Result</p>
              <div class="mt-2">
                <StatusBadge :value="inspection?.result ?? 'pending'" />
              </div>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3">
              <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Defects</p>
              <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                {{ inspection ? `${inspection.failed_items} failed · ${inspection.critical_defects} critical` : 'Not evaluated yet' }}
              </p>
            </div>
          </div>

          <div
            v-if="inspection?.approval_requests?.length"
            class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3"
          >
            <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Approval history</p>
            <div class="mt-3 space-y-3">
              <div
                v-for="approval in inspection.approval_requests"
                :key="approval.id"
                class="flex flex-col gap-2 rounded-2xl border border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3"
              >
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ approval.title }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ approval.summary ?? 'Approval workflow entry' }}</p>
                  </div>
                  <StatusBadge :value="approval.status" />
                </div>
                <p v-if="approval.decision_notes" class="text-xs leading-5 text-slate-600 dark:text-slate-400">
                  Decision notes: {{ approval.decision_notes }}
                </p>
              </div>
            </div>
          </div>

          <div
            v-if="inspection && canClose && inspection.status !== 'closed'"
            class="space-y-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-4"
          >
            <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
              <span class="font-medium">Resolution notes</span>
              <textarea
                v-model="closeNotes"
                class="min-h-24 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
                :disabled="actionLoading"
              />
              <FieldError :errors="errorsFor('resolution_notes')" />
            </label>
            <button
              type="button"
              class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="actionLoading"
              @click="closeInspection"
            >
              {{ actionLoading ? 'Closing...' : 'Close inspection' }}
            </button>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row">
            <RouterLink
              :to="{ name: 'inspections' }"
              class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50 sm:w-auto"
            >
              Back to list
            </RouterLink>
            <button
              v-if="!isViewMode"
              type="submit"
              class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="loading || submitting"
            >
              {{ submitting ? 'Saving...' : 'Record inspection' }}
            </button>
          </div>
        </div>
      </SectionCard>
    </form>

    <SectionCard title="Checklist responses" description="Responses are stored item by item to preserve operational evidence and follow-up context.">
      <div class="space-y-4">
        <div
          v-for="(entry, index) in displayResponses"
          :key="String(entry.key)"
          class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 p-5"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ entry.label }}</p>
              <p
                v-if="!isViewMode && selectedTemplate?.items?.[index]?.description"
                class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
              >
                {{ selectedTemplate.items[index].description }}
              </p>
            </div>
            <span
              v-if="!isViewMode && entry.required"
              class="rounded-full border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:text-slate-400"
            >
              Required
            </span>
          </div>

          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <template v-if="isViewMode">
              <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3">
                <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Response</p>
                <p class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100">{{ responseLabel(entry.responseValue, entry.isPass) }}</p>
              </div>
              <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3">
                <p class="text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Defect severity</p>
                <div class="mt-2">
                  <StatusBadge v-if="entry.defectSeverity" :value="entry.defectSeverity" />
                  <span v-else class="text-sm text-slate-500 dark:text-slate-400">None</span>
                </div>
              </div>
              <div
                v-if="entry.defectSummary || entry.notes"
                class="md:col-span-2 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3 text-sm text-slate-700 dark:text-slate-200"
              >
                <p v-if="entry.defectSummary"><span class="font-medium text-slate-900 dark:text-slate-100">Defect:</span> {{ entry.defectSummary }}</p>
                <p v-if="entry.notes" class="mt-2"><span class="font-medium text-slate-900 dark:text-slate-100">Notes:</span> {{ entry.notes }}</p>
              </div>
            </template>

            <template v-else>
              <label
                v-if="entry.responseType === 'pass_fail'"
                class="space-y-2 text-sm text-slate-700 dark:text-slate-200"
              >
                <span class="font-medium">Outcome</span>
                <select
                  :value="form.responses[index]?.is_pass === null ? '' : form.responses[index]?.is_pass ? 'pass' : 'fail'"
                  class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
                  @change="setPassFail(index, String(($event.target as HTMLSelectElement).value))"
                >
                  <option value="">Select outcome</option>
                  <option value="pass">Pass</option>
                  <option value="fail">Fail</option>
                </select>
                <FieldError :errors="errorsFor(`responses.${index}.is_pass`)" />
              </label>

              <label
                v-else-if="entry.responseType === 'boolean'"
                class="space-y-2 text-sm text-slate-700 dark:text-slate-200"
              >
                <span class="font-medium">Response</span>
                <select
                  :value="form.responses[index]?.response_value === null ? '' : String(form.responses[index]?.response_value)"
                  class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
                  @change="setBooleanResponse(index, String(($event.target as HTMLSelectElement).value))"
                >
                  <option value="">Select response</option>
                  <option value="true">Yes</option>
                  <option value="false">No</option>
                </select>
                <FieldError :errors="errorsFor(`responses.${index}.response_value`)" />
              </label>

              <label
                v-else-if="entry.responseType === 'number'"
                class="space-y-2 text-sm text-slate-700 dark:text-slate-200"
              >
                <span class="font-medium">Response</span>
                <input
                  :value="form.responses[index]?.response_value as number | null"
                  type="number"
                  class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
                  @input="setResponseValue(index, Number(($event.target as HTMLInputElement).value))"
                >
                <FieldError :errors="errorsFor(`responses.${index}.response_value`)" />
              </label>

              <label
                v-else
                class="space-y-2 text-sm text-slate-700 dark:text-slate-200"
              >
                <span class="font-medium">Response</span>
                <textarea
                  :value="String(form.responses[index]?.response_value ?? '')"
                  class="min-h-24 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
                  @input="setResponseValue(index, ($event.target as HTMLTextAreaElement).value)"
                />
                <FieldError :errors="errorsFor(`responses.${index}.response_value`)" />
              </label>

              <label
                class="space-y-2 text-sm text-slate-700 dark:text-slate-200"
              >
                <span class="font-medium">Inspector notes</span>
                <textarea
                  :value="form.responses[index]?.notes ?? ''"
                  class="min-h-24 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
                  @input="setResponseNotes(index, ($event.target as HTMLTextAreaElement).value)"
                />
                <FieldError :errors="errorsFor(`responses.${index}.notes`)" />
              </label>

              <template v-if="entry.responseType === 'pass_fail' && form.responses[index]?.is_pass === false">
                <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
                  <span class="font-medium">Defect severity</span>
                  <select
                    :value="form.responses[index]?.defect_severity ?? ''"
                    class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
                    @change="setDefectSeverity(index, String(($event.target as HTMLSelectElement).value))"
                  >
                    <option value="">No defect severity</option>
                    <option
                      v-for="option in inspectionDefectSeverityOptions"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                  <FieldError :errors="errorsFor(`responses.${index}.defect_severity`)" />
                </label>
                <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
                  <span class="font-medium">Defect summary</span>
                  <input
                    :value="form.responses[index]?.defect_summary ?? ''"
                    class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
                    @input="setDefectSummary(index, ($event.target as HTMLInputElement).value)"
                  >
                  <FieldError :errors="errorsFor(`responses.${index}.defect_summary`)" />
                </label>
              </template>
            </template>
          </div>
        </div>

        <p
          v-if="!displayResponses.length"
          class="rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 px-4 py-6 text-sm text-slate-500 dark:text-slate-400"
        >
          Select an inspection template to load the checklist structure.
        </p>
      </div>
    </SectionCard>
  </div>
</template>
