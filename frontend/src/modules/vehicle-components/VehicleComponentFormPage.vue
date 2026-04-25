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
  vehicleComponentConditionStatusOptions,
  vehicleComponentStatusOptions,
  vehicleComponentTypeOptions,
} from '@/lib/fleet-options'
import { createResource, getResource, updateResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  RetireVehicleComponentPayload,
  VehicleComponent,
  VehicleComponentPayload,
  VehicleComponentSupportData,
  VehicleComponentSupportVehicleOption,
  ReferenceOption,
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
const vehicles = ref<VehicleComponentSupportVehicleOption[]>([])
const serviceProviders = ref<ReferenceOption[]>([])
const vehicleComponent = ref<VehicleComponent | null>(null)

const form = ref<VehicleComponentPayload>({
  vehicle_id: 0,
  service_provider_id: null,
  component_type: 'tyre',
  position_code: '',
  brand: '',
  model: '',
  serial_number: '',
  status: 'active',
  condition_status: 'good',
  installed_at: null,
  installed_odometer: null,
  expected_life_days: null,
  expected_life_km: null,
  reminder_days: null,
  reminder_km: null,
  warranty_expiry_date: null,
  last_inspected_at: null,
  removed_at: null,
  removed_odometer: null,
  removal_reason: '',
  notes: '',
})

const retirement = ref<RetireVehicleComponentPayload>({
  status: 'retired',
  removed_at: null,
  removed_odometer: null,
  removal_reason: '',
  notes: '',
})

const selectedVehicle = computed(() => vehicles.value.find((vehicle) => vehicle.id === form.value.vehicle_id) ?? null)
const isLifecycleClosed = computed(() => ['retired', 'failed'].includes(vehicleComponent.value?.status ?? ''))
const canEditRecord = computed(() => {
  if (!isEditMode.value) return canCreate.value

  return canEdit.value && !isLifecycleClosed.value
})
const canRetireRecord = computed(() => isEditMode.value && canEdit.value && !isLifecycleClosed.value)

function toNullableNumber(value: number | null | undefined): number | null {
  return value === null || value === undefined || Number.isNaN(Number(value)) ? null : Number(value)
}

function buildPayload(): VehicleComponentPayload {
  return {
    vehicle_id: Number(form.value.vehicle_id),
    service_provider_id: form.value.service_provider_id ? Number(form.value.service_provider_id) : null,
    component_type: form.value.component_type,
    position_code: form.value.position_code || null,
    brand: form.value.brand || null,
    model: form.value.model || null,
    serial_number: form.value.serial_number || null,
    status: form.value.status,
    condition_status: form.value.condition_status,
    installed_at: form.value.installed_at || null,
    installed_odometer: toNullableNumber(form.value.installed_odometer),
    expected_life_days: toNullableNumber(form.value.expected_life_days),
    expected_life_km: toNullableNumber(form.value.expected_life_km),
    reminder_days: toNullableNumber(form.value.reminder_days),
    reminder_km: toNullableNumber(form.value.reminder_km),
    warranty_expiry_date: form.value.warranty_expiry_date || null,
    last_inspected_at: form.value.last_inspected_at || null,
    removed_at: form.value.removed_at || null,
    removed_odometer: toNullableNumber(form.value.removed_odometer),
    removal_reason: form.value.removal_reason || null,
    notes: form.value.notes || null,
  }
}

function buildRetirePayload(): RetireVehicleComponentPayload {
  return {
    status: retirement.value.status,
    removed_at: retirement.value.removed_at || null,
    removed_odometer: toNullableNumber(retirement.value.removed_odometer),
    removal_reason: retirement.value.removal_reason || null,
    notes: retirement.value.notes || null,
  }
}

async function loadSupportData() {
  const data = await getResource<VehicleComponentSupportData>('/vehicle-components/support-data')
  vehicles.value = data.vehicles
  serviceProviders.value = data.service_providers

  if (!isEditMode.value && !form.value.vehicle_id && vehicles.value[0]) {
    form.value.vehicle_id = vehicles.value[0].id
    form.value.installed_odometer = vehicles.value[0].odometer_reading
  }
}

async function loadVehicleComponent() {
  if (!isEditMode.value) return

  const record = await getResource<VehicleComponent>(`/vehicle-components/${route.params.id}`)
  vehicleComponent.value = record
  form.value = {
    vehicle_id: record.vehicle_id,
    service_provider_id: record.service_provider_id,
    component_type: record.component_type,
    position_code: record.position_code ?? '',
    brand: record.brand ?? '',
    model: record.model ?? '',
    serial_number: record.serial_number ?? '',
    status: record.status,
    condition_status: record.condition_status,
    installed_at: record.installed_at,
    installed_odometer: record.installed_odometer,
    expected_life_days: record.expected_life_days,
    expected_life_km: record.expected_life_km,
    reminder_days: record.reminder_days,
    reminder_km: record.reminder_km,
    warranty_expiry_date: record.warranty_expiry_date,
    last_inspected_at: record.last_inspected_at,
    removed_at: record.removed_at,
    removed_odometer: record.removed_odometer,
    removal_reason: record.removal_reason ?? '',
    notes: record.notes ?? '',
  }
  retirement.value = {
    status: record.status === 'failed' ? 'failed' : 'retired',
    removed_at: record.removed_at,
    removed_odometer: record.removed_odometer ?? record.vehicle?.odometer_reading ?? null,
    removal_reason: record.removal_reason ?? '',
    notes: record.notes ?? '',
  }
}

async function refreshVehicleComponent() {
  if (!isEditMode.value) return

  await loadVehicleComponent()
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
      vehicleComponent.value = await updateResource<VehicleComponent, VehicleComponentPayload>(`/vehicle-components/${route.params.id}`, payload)
      successMessage.value = 'Vehicle component updated successfully.'
      await refreshVehicleComponent()
    } else {
      await createResource<VehicleComponent, VehicleComponentPayload>('/vehicle-components', payload)
      await router.push({ name: 'vehicle-components' })
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

async function retireComponent() {
  if (!vehicleComponent.value || !canRetireRecord.value) return

  actionLoading.value = true
  errorMessage.value = null
  successMessage.value = null
  fieldErrors.value = {}

  try {
    const response = await api.put(`/vehicle-components/${vehicleComponent.value.id}/retire`, buildRetirePayload())
    vehicleComponent.value = response.data.data as VehicleComponent
    successMessage.value = response.data.message
    await refreshVehicleComponent()
  } catch (error) {
    const apiError = error as ApiError
    errorMessage.value = apiError.message
    fieldErrors.value = apiError.errors ?? {}
  } finally {
    actionLoading.value = false
  }
}

async function removeComponent() {
  if (!vehicleComponent.value || !canDelete.value) return

  if (!globalThis.confirm(`Delete component ${vehicleComponent.value.component_number}?`)) {
    return
  }

  try {
    await api.delete(`/vehicle-components/${vehicleComponent.value.id}`)
    await router.push({ name: 'vehicle-components' })
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

function errorsFor(field: string): string[] | undefined {
  return fieldErrors.value[field]
}

watch(() => form.value.vehicle_id, (vehicleId) => {
  if (!vehicleId || isEditMode.value) return

  const vehicle = vehicles.value.find((item) => item.id === vehicleId)

  if (vehicle && !form.value.installed_odometer) {
    form.value.installed_odometer = vehicle.odometer_reading
  }
})

onMounted(async () => {
  loading.value = true

  try {
    await loadSupportData()
    await loadVehicleComponent()
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
      :title="isEditMode ? 'Manage vehicle component' : 'Add vehicle component'"
      description="Track component lifecycle thresholds so replacement timing stays explicit, auditable, and tenant-configurable."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'vehicle-components' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
        >
          Back to components
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="successMessage"
      title="Vehicle component updated"
      :description="successMessage"
      tone="success"
    />

    <InlineAlert
      v-if="errorMessage"
      title="Unable to save vehicle component"
      :description="errorMessage"
      tone="danger"
    />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.95fr]" @submit.prevent="submit">
      <SectionCard title="Component details" description="Define the asset link, expected lifecycle, and replacement thresholds for the component.">
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
            <span class="font-medium">Service provider</span>
            <select v-model="form.service_provider_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
              <option :value="null">Not assigned</option>
              <option v-for="provider in serviceProviders" :key="provider.id" :value="provider.id">
                {{ provider.label }}{{ provider.secondary ? ` · ${provider.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('service_provider_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Component type</span>
            <select v-model="form.component_type" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
              <option v-for="option in vehicleComponentTypeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('component_type')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Position code</span>
            <input v-model="form.position_code" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
            <FieldError :errors="errorsFor('position_code')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Brand</span>
            <input v-model="form.brand" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
            <FieldError :errors="errorsFor('brand')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Model</span>
            <input v-model="form.model" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
            <FieldError :errors="errorsFor('model')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Serial number</span>
            <input v-model="form.serial_number" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
            <FieldError :errors="errorsFor('serial_number')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Lifecycle status</span>
            <select v-model="form.status" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
              <option v-for="option in vehicleComponentStatusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('status')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Condition</span>
            <select v-model="form.condition_status" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
              <option v-for="option in vehicleComponentConditionStatusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('condition_status')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Installed date</span>
            <input v-model="form.installed_at" type="date" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
            <FieldError :errors="errorsFor('installed_at')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Installed odometer</span>
            <input v-model.number="form.installed_odometer" type="number" min="0" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
            <FieldError :errors="errorsFor('installed_odometer')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Expected life (days)</span>
            <input v-model.number="form.expected_life_days" type="number" min="1" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
            <FieldError :errors="errorsFor('expected_life_days')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Expected life (km)</span>
            <input v-model.number="form.expected_life_km" type="number" min="1" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
            <FieldError :errors="errorsFor('expected_life_km')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Reminder days</span>
            <input v-model.number="form.reminder_days" type="number" min="1" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
            <FieldError :errors="errorsFor('reminder_days')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Reminder kilometres</span>
            <input v-model.number="form.reminder_km" type="number" min="1" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
            <FieldError :errors="errorsFor('reminder_km')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Warranty expiry</span>
            <input v-model="form.warranty_expiry_date" type="date" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
            <FieldError :errors="errorsFor('warranty_expiry_date')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Last inspected</span>
            <input v-model="form.last_inspected_at" type="date" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord">
            <FieldError :errors="errorsFor('last_inspected_at')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Notes</span>
            <textarea v-model="form.notes" class="min-h-28 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || !canEditRecord" />
            <FieldError :errors="errorsFor('notes')" />
          </label>
        </div>
      </SectionCard>

      <div class="space-y-6">
        <SectionCard title="Lifecycle context" description="Review replacement posture and close out components when they leave service.">
          <div class="space-y-4 text-sm text-slate-700 dark:text-slate-200">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3">
              <p class="text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Selected vehicle</p>
              <p class="mt-1 text-base font-semibold text-slate-900 dark:text-slate-100">{{ selectedVehicle?.label ?? 'No vehicle selected' }}</p>
              <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Current odometer: {{ selectedVehicle ? `${selectedVehicle.odometer_reading.toLocaleString()} km` : '—' }}
              </p>
            </div>

            <div v-if="vehicleComponent" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Current due state</p>
                  <p class="mt-1 text-base font-semibold text-slate-900 dark:text-slate-100">{{ vehicleComponent.component_number }}</p>
                </div>
                <StatusBadge :value="vehicleComponent.due_status" />
              </div>
              <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                Next replacement:
                <span v-if="vehicleComponent.next_replacement_at">{{ vehicleComponent.next_replacement_at }}</span>
                <span v-else>No date</span>
                <span v-if="vehicleComponent.next_replacement_km !== null"> · {{ vehicleComponent.next_replacement_km.toLocaleString() }} km</span>
              </p>
              <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Remaining:
                <span v-if="vehicleComponent.days_until_replacement !== null">{{ vehicleComponent.days_until_replacement }} days</span>
                <span v-if="vehicleComponent.days_until_replacement !== null && vehicleComponent.km_until_replacement !== null"> · </span>
                <span v-if="vehicleComponent.km_until_replacement !== null">{{ vehicleComponent.km_until_replacement.toLocaleString() }} km</span>
                <span v-if="vehicleComponent.days_until_replacement === null && vehicleComponent.km_until_replacement === null">No threshold calculated</span>
              </p>
            </div>
            <p v-else class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm leading-6 text-slate-600 dark:text-slate-400">
              New components compute replacement thresholds after saving based on installed baseline and expected life values.
            </p>

            <div v-if="canRetireRecord" class="space-y-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-4">
              <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Retire or fail component</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Use this when the component leaves service, fails in operation, or is replaced.</p>
              </div>
              <div class="grid gap-4 md:grid-cols-2">
                <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
                  <span class="font-medium">Lifecycle outcome</span>
                  <select v-model="retirement.status" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading">
                    <option value="retired">Retired</option>
                    <option value="failed">Failed</option>
                  </select>
                </label>
                <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
                  <span class="font-medium">Removed date</span>
                  <input v-model="retirement.removed_at" type="date" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading">
                  <FieldError :errors="errorsFor('removed_at')" />
                </label>
                <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
                  <span class="font-medium">Removed odometer</span>
                  <input v-model.number="retirement.removed_odometer" type="number" min="0" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading">
                  <FieldError :errors="errorsFor('removed_odometer')" />
                </label>
                <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
                  <span class="font-medium">Removal reason</span>
                  <textarea v-model="retirement.removal_reason" class="min-h-24 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || actionLoading" />
                  <FieldError :errors="errorsFor('removal_reason')" />
                </label>
              </div>
            </div>

            <div class="flex flex-col gap-3">
              <div class="flex flex-col gap-3 sm:flex-row">
                <RouterLink :to="{ name: 'vehicle-components' }" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50 sm:w-auto">
                  Cancel
                </RouterLink>
                <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading || submitting || (isEditMode ? !canEditRecord : !canCreate)">
                  {{ submitting ? 'Saving...' : isEditMode ? 'Save component' : 'Create component' }}
                </button>
              </div>

              <div v-if="isEditMode" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <button
                  v-if="canRetireRecord"
                  type="button"
                  class="rounded-2xl border border-amber-300 dark:border-amber-800/60 px-4 py-3 text-sm font-semibold text-amber-800 dark:text-amber-200 transition hover:bg-amber-50 dark:hover:bg-amber-950/40 disabled:cursor-not-allowed disabled:opacity-60"
                  :disabled="actionLoading"
                  @click="retireComponent"
                >
                  {{ actionLoading ? 'Processing...' : 'Retire component' }}
                </button>
                <button
                  v-if="canDelete && !isLifecycleClosed"
                  type="button"
                  class="rounded-2xl border border-rose-300 dark:border-rose-800/60 px-4 py-3 text-sm font-semibold text-rose-700 dark:text-rose-200 transition hover:bg-rose-50 dark:hover:bg-rose-950/40"
                  @click="removeComponent"
                >
                  Delete component
                </button>
              </div>
            </div>
          </div>
        </SectionCard>
      </div>
    </form>
  </div>
</template>
