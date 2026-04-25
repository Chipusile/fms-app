<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { createResource, getResource, updateResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  CreateTripPayload,
  ReferenceOption,
  Trip,
  TripSupportData,
  TripSupportVehicleOption,
  UpdateTripPayload,
} from '@/types'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const isEditMode = computed(() => Boolean(route.params.id))
const canEdit = computed(() => auth.hasPermission('trips.update'))
const canCreate = computed(() => auth.hasPermission('trips.create'))
const loading = ref(false)
const submitting = ref(false)
const actionLoading = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const vehicles = ref<TripSupportVehicleOption[]>([])
const drivers = ref<ReferenceOption[]>([])
const tripApprovalRequired = ref(true)
const trip = ref<Trip | null>(null)

const approvalNotes = ref('')
const rejectionReason = ref('')
const cancellationReason = ref('')
const startOdometer = ref<number | null>(null)
const endOdometer = ref<number | null>(null)
const completionNotes = ref('')

const form = ref<CreateTripPayload>({
  vehicle_id: 0,
  driver_id: 0,
  purpose: '',
  origin: '',
  destination: '',
  scheduled_start: nextDateTimeLocal(1),
  scheduled_end: nextDateTimeLocal(2),
  passengers: null,
  cargo_description: '',
  notes: '',
})

const tripDriverUserId = computed(() => {
  return (trip.value?.driver as { user_id?: number | null } | null | undefined)?.user_id ?? null
})

const canApprove = computed(() => trip.value?.status === 'requested' && auth.hasPermission('trips.approve'))
const canStart = computed(() => {
  if (!trip.value) return false
  return trip.value.status === 'approved' && (
    auth.hasPermission('trips.update') || tripDriverUserId.value === auth.user?.id
  )
})
const canComplete = computed(() => {
  if (!trip.value) return false
  return trip.value.status === 'in_progress' && (
    auth.hasPermission('trips.update') || tripDriverUserId.value === auth.user?.id
  )
})
const canCancel = computed(() => {
  if (!trip.value) return false
  return !['completed', 'cancelled'].includes(trip.value.status) && (
    auth.hasPermission('trips.delete') || trip.value.requested_by === auth.user?.id
  )
})

const selectedVehicle = computed(() => vehicles.value.find((vehicle) => vehicle.id === form.value.vehicle_id) ?? null)

function nextDateTimeLocal(dayOffset: number): string {
  const value = new Date()
  value.setDate(value.getDate() + dayOffset)
  value.setHours(dayOffset === 1 ? 8 : 17, 0, 0, 0)
  return value.toISOString().slice(0, 16)
}

function toDateTimeLocal(value: string | null): string {
  return value ? value.slice(0, 16) : ''
}

async function loadSupportData() {
  const data = await getResource<TripSupportData>('/trips/support-data')
  vehicles.value = data.vehicles
  drivers.value = data.drivers
  tripApprovalRequired.value = data.trip_approval_required

  if (!isEditMode.value) {
    if (!form.value.vehicle_id && vehicles.value[0]) {
      form.value.vehicle_id = vehicles.value[0].id
      startOdometer.value = vehicles.value[0].odometer_reading
      endOdometer.value = vehicles.value[0].odometer_reading
    }

    if (!form.value.driver_id && drivers.value[0]) {
      form.value.driver_id = drivers.value[0].id
    }
  }
}

async function loadTrip() {
  if (!isEditMode.value) return

  const record = await getResource<Trip>(`/trips/${route.params.id}`)
  trip.value = record
  form.value = {
    vehicle_id: record.vehicle_id,
    driver_id: record.driver_id,
    purpose: record.purpose,
    origin: record.origin,
    destination: record.destination,
    scheduled_start: toDateTimeLocal(record.scheduled_start),
    scheduled_end: toDateTimeLocal(record.scheduled_end),
    passengers: record.passengers,
    cargo_description: record.cargo_description ?? '',
    notes: record.notes ?? '',
  }
  startOdometer.value = record.start_odometer ?? vehicles.value.find((vehicle) => vehicle.id === record.vehicle_id)?.odometer_reading ?? null
  endOdometer.value = record.end_odometer ?? null
  completionNotes.value = record.notes ?? ''
}

async function refreshTrip() {
  if (!isEditMode.value) return

  await loadTrip()
}

async function submit() {
  if (isEditMode.value && !canEdit.value) {
    return
  }

  if (!isEditMode.value && !canCreate.value) {
    return
  }

  submitting.value = true
  errorMessage.value = null
  successMessage.value = null
  fieldErrors.value = {}

  try {
    if (isEditMode.value) {
      const record = await updateResource<Trip, UpdateTripPayload>(`/trips/${route.params.id}`, form.value)
      trip.value = record
      successMessage.value = 'Trip details updated successfully.'
    } else {
      await createResource<Trip, CreateTripPayload>('/trips', form.value)
      await router.push({ name: 'trips' })
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

async function runAction(action: 'approve' | 'reject' | 'start' | 'complete' | 'cancel', payload: Record<string, unknown>) {
  if (!trip.value) return

  actionLoading.value = true
  errorMessage.value = null
  successMessage.value = null
  fieldErrors.value = {}

  try {
    const response = await api.put(`/trips/${trip.value.id}/${action}`, payload)
    trip.value = response.data.data as Trip
    successMessage.value = response.data.message
    await refreshTrip()
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

watch(selectedVehicle, (vehicle) => {
  if (!vehicle || isEditMode.value) return

  startOdometer.value = vehicle.odometer_reading
  endOdometer.value = vehicle.odometer_reading
})

onMounted(async () => {
  loading.value = true

  try {
    await loadSupportData()
    await loadTrip()
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
      :title="isEditMode ? 'Manage trip' : 'Create trip'"
      description="Trips coordinate vehicle use, driver allocation, routing intent, and operational approvals in one auditable workflow."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'trips' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
        >
          Back to trips
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="tripApprovalRequired && !isEditMode"
      title="Approval flow is enabled"
      description="New trips will be created in requested status and must be approved before they can start."
      tone="info"
    />

    <InlineAlert
      v-if="successMessage"
      title="Trip updated"
      :description="successMessage"
      tone="success"
    />

    <InlineAlert
      v-if="errorMessage"
      title="Unable to process trip"
      :description="errorMessage"
      tone="danger"
    />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.95fr]" @submit.prevent="submit">
      <SectionCard title="Trip details" description="Define the route, timing, and purpose for this journey request.">
        <div class="grid gap-4 md:grid-cols-2">
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
            <span class="font-medium">Driver</span>
            <select v-model="form.driver_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
              <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                {{ driver.label }}{{ driver.secondary ? ` · ${driver.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('driver_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Purpose</span>
            <input v-model="form.purpose" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('purpose')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Origin</span>
            <input v-model="form.origin" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('origin')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Destination</span>
            <input v-model="form.destination" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('destination')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Scheduled start</span>
            <input v-model="form.scheduled_start" type="datetime-local" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('scheduled_start')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Scheduled end</span>
            <input v-model="form.scheduled_end" type="datetime-local" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('scheduled_end')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Passengers</span>
            <input v-model.number="form.passengers" type="number" min="1" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('passengers')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Cargo description</span>
            <input v-model="form.cargo_description" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('cargo_description')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Notes</span>
            <textarea v-model="form.notes" class="min-h-28 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)" />
            <FieldError :errors="errorsFor('notes')" />
          </label>
        </div>
      </SectionCard>

      <div class="space-y-6">
        <SectionCard title="Workflow status" description="Track approval and execution milestones without leaving the record.">
          <div class="space-y-4 text-sm text-slate-700 dark:text-slate-200">
            <div v-if="trip" class="flex items-center justify-between rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3">
              <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Current status</p>
                <p class="mt-1 text-base font-semibold text-slate-900 dark:text-slate-100">{{ trip.trip_number }}</p>
              </div>
              <StatusBadge :value="trip.status" />
            </div>
            <p v-else class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-xs leading-5 text-slate-600 dark:text-slate-400">
              Save the trip first to expose approval and execution controls.
            </p>

            <div v-if="trip?.rejection_reason" class="rounded-2xl border border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/40 px-4 py-3 text-xs leading-5 text-rose-800 dark:text-rose-200">
              Rejection reason: {{ trip.rejection_reason }}
            </div>
            <div v-if="trip?.cancellation_reason" class="rounded-2xl border border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/40 px-4 py-3 text-xs leading-5 text-rose-800 dark:text-rose-200">
              Cancellation reason: {{ trip.cancellation_reason }}
            </div>
            <div v-if="trip?.actual_start || trip?.actual_end" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-xs leading-5 text-slate-600 dark:text-slate-400">
              <p>Actual start: {{ trip.actual_start ? new Date(trip.actual_start).toLocaleString() : 'Not started' }}</p>
              <p>Actual end: {{ trip.actual_end ? new Date(trip.actual_end).toLocaleString() : 'Not completed' }}</p>
              <p>Distance: {{ trip.distance_km ?? '—' }} km</p>
            </div>

            <template v-if="isEditMode">
              <div v-if="canApprove" class="space-y-3 rounded-2xl border border-slate-200 dark:border-slate-800 p-4">
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Approval</p>
                <textarea v-model="approvalNotes" class="min-h-24 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" placeholder="Optional approval note" :disabled="actionLoading" />
                <button type="button" class="rounded-2xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-60" :disabled="actionLoading" @click="runAction('approve', { notes: approvalNotes || null })">
                  Approve trip
                </button>
              </div>

              <div v-if="canApprove" class="space-y-3 rounded-2xl border border-slate-200 dark:border-slate-800 p-4">
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Reject request</p>
                <textarea v-model="rejectionReason" class="min-h-24 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" placeholder="Reason for rejection" :disabled="actionLoading" />
                <FieldError :errors="errorsFor('reason')" />
                <button type="button" class="rounded-2xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-500 disabled:opacity-60" :disabled="actionLoading || !rejectionReason" @click="runAction('reject', { reason: rejectionReason })">
                  Reject trip
                </button>
              </div>

              <div v-if="canStart" class="space-y-3 rounded-2xl border border-slate-200 dark:border-slate-800 p-4">
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Start trip</p>
                <input v-model.number="startOdometer" type="number" min="0" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :placeholder="selectedVehicle ? `Current vehicle odometer ${selectedVehicle.odometer_reading}` : 'Start odometer'" :disabled="actionLoading">
                <FieldError :errors="errorsFor('start_odometer')" />
                <button type="button" class="rounded-2xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-500 disabled:opacity-60" :disabled="actionLoading || startOdometer === null" @click="runAction('start', { start_odometer: startOdometer })">
                  Start trip
                </button>
              </div>

              <div v-if="canComplete" class="space-y-3 rounded-2xl border border-slate-200 dark:border-slate-800 p-4">
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Complete trip</p>
                <input v-model.number="endOdometer" type="number" min="0" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" placeholder="End odometer" :disabled="actionLoading">
                <FieldError :errors="errorsFor('end_odometer')" />
                <textarea v-model="completionNotes" class="min-h-24 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" placeholder="Completion notes" :disabled="actionLoading" />
                <button type="button" class="rounded-2xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-60" :disabled="actionLoading || endOdometer === null" @click="runAction('complete', { end_odometer: endOdometer, notes: completionNotes || null })">
                  Complete trip
                </button>
              </div>

              <div v-if="canCancel" class="space-y-3 rounded-2xl border border-slate-200 dark:border-slate-800 p-4">
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Cancel trip</p>
                <textarea v-model="cancellationReason" class="min-h-24 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" placeholder="Reason for cancellation" :disabled="actionLoading" />
                <button type="button" class="rounded-2xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-500 disabled:opacity-60" :disabled="actionLoading || !cancellationReason" @click="runAction('cancel', { reason: cancellationReason })">
                  Cancel trip
                </button>
              </div>
            </template>

            <div class="flex flex-col gap-3 sm:flex-row">
              <RouterLink
                :to="{ name: 'trips' }"
                class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50 sm:w-auto"
              >
                Back
              </RouterLink>
              <button
                type="submit"
                class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="loading || submitting || (isEditMode ? !canEdit : !canCreate)"
              >
                {{ submitting ? 'Saving...' : isEditMode ? 'Save trip details' : 'Create trip' }}
              </button>
            </div>
          </div>
        </SectionCard>
      </div>
    </form>
  </div>
</template>
