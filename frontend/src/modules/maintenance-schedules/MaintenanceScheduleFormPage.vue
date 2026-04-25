<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { maintenanceScheduleStatusOptions, maintenanceScheduleTypeOptions } from '@/lib/fleet-options'
import { createResource, getResource, updateResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  MaintenanceSchedule,
  MaintenanceSchedulePayload,
  MaintenanceScheduleSupportData,
  MaintenanceScheduleSupportVehicleOption,
  ReferenceOption,
} from '@/types'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const isEditMode = computed(() => Boolean(route.params.id))
const canEdit = computed(() => auth.hasPermission('maintenance.update'))
const canCreate = computed(() => auth.hasPermission('maintenance.create'))
const loading = ref(false)
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const vehicles = ref<MaintenanceScheduleSupportVehicleOption[]>([])
const serviceProviders = ref<ReferenceOption[]>([])
const schedule = ref<MaintenanceSchedule | null>(null)

const form = ref<MaintenanceSchedulePayload>({
  vehicle_id: 0,
  service_provider_id: null,
  title: '',
  schedule_type: 'preventive',
  status: 'active',
  interval_days: null,
  interval_km: null,
  reminder_days: null,
  reminder_km: null,
  last_performed_at: null,
  last_performed_km: null,
  notes: '',
})

const selectedVehicle = computed(() => vehicles.value.find((vehicle) => vehicle.id === form.value.vehicle_id) ?? null)

function toDateInput(value: string | null): string | null {
  return value ? value.slice(0, 10) : null
}

function toNullableNumber(value: number | null | undefined): number | null {
  return value === null || value === undefined || Number.isNaN(Number(value)) ? null : Number(value)
}

function buildPayload(): MaintenanceSchedulePayload {
  return {
    vehicle_id: Number(form.value.vehicle_id),
    service_provider_id: form.value.service_provider_id ? Number(form.value.service_provider_id) : null,
    title: form.value.title,
    schedule_type: form.value.schedule_type,
    status: form.value.status,
    interval_days: toNullableNumber(form.value.interval_days),
    interval_km: toNullableNumber(form.value.interval_km),
    reminder_days: toNullableNumber(form.value.reminder_days),
    reminder_km: toNullableNumber(form.value.reminder_km),
    last_performed_at: form.value.last_performed_at || null,
    last_performed_km: toNullableNumber(form.value.last_performed_km),
    notes: form.value.notes || null,
  }
}

async function loadSupportData() {
  const data = await getResource<MaintenanceScheduleSupportData>('/maintenance-schedules/support-data')
  vehicles.value = data.vehicles
  serviceProviders.value = data.service_providers

  if (!isEditMode.value && !form.value.vehicle_id && vehicles.value[0]) {
    form.value.vehicle_id = vehicles.value[0].id
    form.value.last_performed_km = vehicles.value[0].odometer_reading
  }
}

async function loadSchedule() {
  if (!isEditMode.value) return

  const record = await getResource<MaintenanceSchedule>(`/maintenance-schedules/${route.params.id}`)
  schedule.value = record
  form.value = {
    vehicle_id: record.vehicle_id,
    service_provider_id: record.service_provider_id,
    title: record.title,
    schedule_type: record.schedule_type,
    status: record.status,
    interval_days: record.interval_days,
    interval_km: record.interval_km,
    reminder_days: record.reminder_days,
    reminder_km: record.reminder_km,
    last_performed_at: toDateInput(record.last_performed_at),
    last_performed_km: record.last_performed_km,
    notes: record.notes ?? '',
  }
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
      schedule.value = await updateResource<MaintenanceSchedule, MaintenanceSchedulePayload>(`/maintenance-schedules/${route.params.id}`, payload)
      successMessage.value = 'Maintenance schedule updated successfully.'
    } else {
      await createResource<MaintenanceSchedule, MaintenanceSchedulePayload>('/maintenance-schedules', payload)
      await router.push({ name: 'maintenance-schedules' })
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

function errorsFor(field: string): string[] | undefined {
  return fieldErrors.value[field]
}

onMounted(async () => {
  loading.value = true

  try {
    await loadSupportData()
    await loadSchedule()
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
      :title="isEditMode ? 'Manage maintenance schedule' : 'Create maintenance schedule'"
      description="Use schedules to define recurring service cadence without hardcoding rules into the application."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'maintenance-schedules' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
        >
          Back to schedules
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="successMessage"
      title="Schedule updated"
      :description="successMessage"
      tone="success"
    />

    <InlineAlert
      v-if="errorMessage"
      title="Unable to save schedule"
      :description="errorMessage"
      tone="danger"
    />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.9fr]" @submit.prevent="submit">
      <SectionCard title="Schedule details" description="Define what asset is covered, the cadence used, and when reminders should begin.">
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
            <span class="font-medium">Preferred service provider</span>
            <select v-model="form.service_provider_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
              <option :value="null">No preferred provider</option>
              <option v-for="provider in serviceProviders" :key="provider.id" :value="provider.id">
                {{ provider.label }}{{ provider.secondary ? ` · ${provider.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('service_provider_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Title</span>
            <input v-model="form.title" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('title')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Schedule type</span>
            <select v-model="form.schedule_type" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
              <option v-for="option in maintenanceScheduleTypeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('schedule_type')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Status</span>
            <select v-model="form.status" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
              <option v-for="option in maintenanceScheduleStatusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('status')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Interval days</span>
            <input v-model.number="form.interval_days" type="number" min="1" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('interval_days')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Interval kilometres</span>
            <input v-model.number="form.interval_km" type="number" min="1" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('interval_km')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Reminder days</span>
            <input v-model.number="form.reminder_days" type="number" min="1" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('reminder_days')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Reminder kilometres</span>
            <input v-model.number="form.reminder_km" type="number" min="1" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('reminder_km')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Last performed date</span>
            <input v-model="form.last_performed_at" type="date" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('last_performed_at')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Last performed odometer</span>
            <input v-model.number="form.last_performed_km" type="number" min="0" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('last_performed_km')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Notes</span>
            <textarea v-model="form.notes" class="min-h-28 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)" />
            <FieldError :errors="errorsFor('notes')" />
          </label>
        </div>
      </SectionCard>

      <div class="space-y-6">
        <SectionCard title="Operational context" description="Keep planners aware of the current asset state while maintaining the schedule.">
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

            <div v-if="schedule" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Current due state</p>
                  <p class="mt-1 text-base font-semibold text-slate-900 dark:text-slate-100">{{ schedule.title }}</p>
                </div>
                <StatusBadge :value="schedule.due_status" />
              </div>
              <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                Next due: {{ schedule.next_due_at ? schedule.next_due_at.slice(0, 10) : 'No date' }}
                <span v-if="schedule.next_due_km !== null"> · {{ schedule.next_due_km.toLocaleString() }} km</span>
              </p>
            </div>

            <p v-else class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm leading-6 text-slate-600 dark:text-slate-400">
              New schedules become actionable once saved and will then compute upcoming or overdue status automatically.
            </p>

            <div class="flex flex-col gap-3 sm:flex-row">
              <RouterLink :to="{ name: 'maintenance-schedules' }" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50 sm:w-auto">
                Cancel
              </RouterLink>
              <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading || submitting || (isEditMode ? !canEdit : !canCreate)">
                {{ submitting ? 'Saving...' : isEditMode ? 'Save schedule' : 'Create schedule' }}
              </button>
            </div>
          </div>
        </SectionCard>
      </div>
    </form>
  </div>
</template>
