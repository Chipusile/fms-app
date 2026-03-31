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
  incidentSeverityOptions,
  incidentTypeOptions,
} from '@/lib/fleet-options'
import { createResource, destroyResource, getResource, updateResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  ApiResponse,
  CreateIncidentPayload,
  Incident,
  IncidentSupportData,
  UpdateIncidentPayload,
} from '@/types'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const isEditMode = computed(() => Boolean(route.params.id))
const canCreate = computed(() => auth.hasPermission('incidents.create'))
const canEdit = computed(() => auth.hasPermission('incidents.update'))
const canDelete = computed(() => auth.hasPermission('incidents.delete'))
const loading = ref(false)
const submitting = ref(false)
const actionLoading = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const supportData = ref<IncidentSupportData | null>(null)
const incident = ref<Incident | null>(null)
const resolutionNotes = ref('')

const form = ref<CreateIncidentPayload>({
  vehicle_id: 0,
  driver_id: null,
  trip_id: null,
  assigned_to: null,
  incident_type: 'accident',
  severity: 'low',
  occurred_at: new Date().toISOString().slice(0, 16),
  reported_at: new Date().toISOString().slice(0, 16),
  location: '',
  description: '',
  immediate_action: '',
  injury_count: 0,
  estimated_cost: null,
})

const vehicles = computed(() => supportData.value?.vehicles ?? [])
const drivers = computed(() => supportData.value?.drivers ?? [])
const trips = computed(() => supportData.value?.trips ?? [])
const assignees = computed(() => supportData.value?.assignees ?? [])
const filteredTrips = computed(() => trips.value.filter((trip) => !form.value.vehicle_id || trip.vehicle_id === form.value.vehicle_id))

const canResolve = computed(() => {
  if (!incident.value || !canEdit.value) return false
  return !['resolved', 'closed', 'rejected'].includes(incident.value.status)
})

const canClose = computed(() => {
  if (!incident.value || !canEdit.value) return false
  return incident.value.status === 'resolved'
})

function toDateTimeLocal(value: string | null): string {
  return value ? value.slice(0, 16) : ''
}

async function loadSupportData() {
  const data = await getResource<IncidentSupportData>('/incidents/support-data')
  supportData.value = data

  if (!isEditMode.value) {
    if (!form.value.vehicle_id && data.vehicles[0]) {
      form.value.vehicle_id = data.vehicles[0].id
    }

    if (!form.value.assigned_to && data.assignees[0]) {
      form.value.assigned_to = data.assignees[0].id
    }
  }
}

async function loadIncident() {
  if (!isEditMode.value) {
    return
  }

  const record = await getResource<Incident>(`/incidents/${route.params.id}`)
  incident.value = record
  resolutionNotes.value = record.resolution_notes ?? ''
  form.value = {
    vehicle_id: record.vehicle_id,
    driver_id: record.driver_id,
    trip_id: record.trip_id,
    assigned_to: record.assigned_to,
    incident_type: record.incident_type,
    severity: record.severity,
    occurred_at: toDateTimeLocal(record.occurred_at),
    reported_at: toDateTimeLocal(record.reported_at),
    location: record.location ?? '',
    description: record.description,
    immediate_action: record.immediate_action ?? '',
    injury_count: record.injury_count ?? 0,
    estimated_cost: record.estimated_cost === null ? null : Number(record.estimated_cost),
  }
}

async function submit() {
  if (!isEditMode.value && !canCreate.value) {
    return
  }

  if (isEditMode.value && !canEdit.value) {
    return
  }

  submitting.value = true
  errorMessage.value = null
  successMessage.value = null
  fieldErrors.value = {}

  try {
    if (isEditMode.value) {
      const updated = await updateResource<Incident, UpdateIncidentPayload>(`/incidents/${route.params.id}`, form.value)
      incident.value = updated
      successMessage.value = 'Incident updated successfully.'
      await loadIncident()
    } else {
      const created = await createResource<Incident, CreateIncidentPayload>('/incidents', form.value)
      await router.push({ name: 'incidents.edit', params: { id: String(created.id) } })
    }
  } catch (error) {
    const apiError = error as ApiError
    errorMessage.value = apiError.message
    fieldErrors.value = apiError.errors ?? {}
  } finally {
    submitting.value = false
  }
}

async function resolveIncident() {
  if (!incident.value || !canResolve.value) {
    return
  }

  actionLoading.value = true
  errorMessage.value = null
  successMessage.value = null
  fieldErrors.value = {}

  try {
    const response = await api.put<ApiResponse<Incident>>(`/incidents/${incident.value.id}/resolve`, {
      resolution_notes: resolutionNotes.value,
    })
    incident.value = response.data.data
    successMessage.value = response.data.message
    await loadIncident()
  } catch (error) {
    const apiError = error as ApiError
    errorMessage.value = apiError.message
    fieldErrors.value = apiError.errors ?? {}
  } finally {
    actionLoading.value = false
  }
}

async function closeIncident() {
  if (!incident.value || !canClose.value) {
    return
  }

  actionLoading.value = true
  errorMessage.value = null
  successMessage.value = null

  try {
    const response = await api.put<ApiResponse<Incident>>(`/incidents/${incident.value.id}/close`)
    incident.value = response.data.data
    successMessage.value = response.data.message
    await loadIncident()
  } catch (error) {
    const apiError = error as ApiError
    errorMessage.value = apiError.message
  } finally {
    actionLoading.value = false
  }
}

async function deleteIncident() {
  if (!incident.value || !canDelete.value || !globalThis.confirm(`Delete incident ${incident.value.incident_number}?`)) {
    return
  }

  actionLoading.value = true
  errorMessage.value = null

  try {
    await destroyResource(`/incidents/${incident.value.id}`)
    await router.push({ name: 'incidents' })
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    actionLoading.value = false
  }
}

function errorsFor(field: string): string[] | undefined {
  return fieldErrors.value[field]
}

watch(() => form.value.trip_id, (tripId) => {
  if (isEditMode.value) {
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
    await loadIncident()
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
      :title="isEditMode ? (incident?.incident_number ?? 'Manage incident') : 'Report incident'"
      description="Incident workflows keep operational exceptions visible from first report through review, remediation, and closure."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'incidents' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
        >
          Back to incidents
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="successMessage"
      title="Incident updated"
      :description="successMessage"
      tone="success"
    />

    <InlineAlert
      v-if="errorMessage"
      title="Unable to process incident"
      :description="errorMessage"
      tone="danger"
    />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.95fr]" @submit.prevent="submit">
      <SectionCard title="Incident details" description="Capture the operational context, type, severity, and narrative for this event.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Vehicle</span>
            <select
              v-model="form.vehicle_id"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || (isEditMode && !canEdit)"
            >
              <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                {{ vehicle.label }}{{ vehicle.secondary ? ` · ${vehicle.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('vehicle_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Driver</span>
            <select
              v-model="form.driver_id"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || (isEditMode && !canEdit)"
            >
              <option :value="null">No driver linked</option>
              <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                {{ driver.label }}{{ driver.secondary ? ` · ${driver.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('driver_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Trip</span>
            <select
              v-model="form.trip_id"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || (isEditMode && !canEdit)"
            >
              <option :value="null">Not linked to a trip</option>
              <option v-for="trip in filteredTrips" :key="trip.id" :value="trip.id">
                {{ trip.label }}{{ trip.secondary ? ` · ${trip.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('trip_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Assigned to</span>
            <select
              v-model="form.assigned_to"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || (isEditMode && !canEdit)"
            >
              <option :value="null">Unassigned</option>
              <option v-for="assignee in assignees" :key="assignee.id" :value="assignee.id">
                {{ assignee.label }}{{ assignee.secondary ? ` · ${assignee.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('assigned_to')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Incident type</span>
            <select
              v-model="form.incident_type"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || (isEditMode && !canEdit)"
            >
              <option v-for="option in incidentTypeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('incident_type')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Severity</span>
            <select
              v-model="form.severity"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || (isEditMode && !canEdit)"
            >
              <option v-for="option in incidentSeverityOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('severity')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Occurred at</span>
            <input
              v-model="form.occurred_at"
              type="datetime-local"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || (isEditMode && !canEdit)"
            >
            <FieldError :errors="errorsFor('occurred_at')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Reported at</span>
            <input
              v-model="form.reported_at"
              type="datetime-local"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || (isEditMode && !canEdit)"
            >
            <FieldError :errors="errorsFor('reported_at')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 md:col-span-2">
            <span class="font-medium">Location</span>
            <input
              v-model="form.location"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || (isEditMode && !canEdit)"
            >
            <FieldError :errors="errorsFor('location')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 md:col-span-2">
            <span class="font-medium">Description</span>
            <textarea
              v-model="form.description"
              class="min-h-32 w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || (isEditMode && !canEdit)"
            />
            <FieldError :errors="errorsFor('description')" />
          </label>
        </div>
      </SectionCard>

      <SectionCard title="Impact and workflow" description="Keep response actions, approval history, and closure controls in one view.">
        <div class="space-y-4 text-sm text-slate-700">
          <div
            v-if="incident"
            class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
          >
            <div>
              <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Current status</p>
              <p class="mt-1 text-base font-semibold text-slate-900">{{ incident.incident_number }}</p>
            </div>
            <StatusBadge :value="incident.status" />
          </div>
          <p
            v-else
            class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600"
          >
            Save the incident first to expose resolution and closure actions.
          </p>

          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Immediate action</span>
            <textarea
              v-model="form.immediate_action"
              class="min-h-24 w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || (isEditMode && !canEdit)"
            />
            <FieldError :errors="errorsFor('immediate_action')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Injury count</span>
            <input
              v-model.number="form.injury_count"
              type="number"
              min="0"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || (isEditMode && !canEdit)"
            >
            <FieldError :errors="errorsFor('injury_count')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Estimated cost</span>
            <input
              v-model.number="form.estimated_cost"
              type="number"
              min="0"
              step="0.01"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || (isEditMode && !canEdit)"
            >
            <FieldError :errors="errorsFor('estimated_cost')" />
          </label>

          <div
            v-if="incident?.approval_requests?.length"
            class="rounded-2xl border border-slate-200 bg-white px-4 py-3"
          >
            <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Approval history</p>
            <div class="mt-3 space-y-3">
              <div
                v-for="approval in incident.approval_requests"
                :key="approval.id"
                class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3"
              >
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-slate-900">{{ approval.title }}</p>
                    <p class="text-xs text-slate-500">{{ approval.summary ?? 'Approval workflow entry' }}</p>
                  </div>
                  <StatusBadge :value="approval.status" />
                </div>
                <p v-if="approval.decision_notes" class="mt-2 text-xs leading-5 text-slate-600">
                  Decision notes: {{ approval.decision_notes }}
                </p>
              </div>
            </div>
          </div>

          <label
            v-if="incident && canResolve"
            class="space-y-2 text-sm text-slate-700"
          >
            <span class="font-medium">Resolution notes</span>
            <textarea
              v-model="resolutionNotes"
              class="min-h-24 w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="actionLoading"
            />
            <FieldError :errors="errorsFor('resolution_notes')" />
          </label>

          <div class="flex flex-col gap-3">
            <button
              v-if="canResolve"
              type="button"
              class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="actionLoading"
              @click="resolveIncident"
            >
              {{ actionLoading ? 'Processing...' : 'Resolve incident' }}
            </button>
            <button
              v-if="canClose"
              type="button"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="actionLoading"
              @click="closeIncident"
            >
              Close incident
            </button>
            <button
              v-if="incident && canDelete"
              type="button"
              class="w-full rounded-2xl border border-rose-300 px-4 py-3 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="actionLoading"
              @click="deleteIncident"
            >
              Delete incident
            </button>
          </div>

          <div class="flex flex-col gap-3 sm:flex-row">
            <RouterLink
              :to="{ name: 'incidents' }"
              class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto"
            >
              Back to list
            </RouterLink>
            <button
              type="submit"
              class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="loading || submitting || (isEditMode && !canEdit)"
            >
              {{ submitting ? 'Saving...' : isEditMode ? 'Save incident' : 'Report incident' }}
            </button>
          </div>
        </div>
      </SectionCard>
    </form>
  </div>
</template>
