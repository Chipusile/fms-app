<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import { createResource, getResource, updateResource } from '@/lib/resource-client'
import type {
  ApiError,
  CreateFuelLogPayload,
  FuelLog,
  FuelLogSupportData,
  FuelSupportTripOption,
  FuelSupportVehicleOption,
  ReferenceOption,
  UpdateFuelLogPayload,
} from '@/types'

const route = useRoute()
const router = useRouter()
const isEditMode = computed(() => Boolean(route.params.id))
const loading = ref(false)
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const vehicles = ref<FuelSupportVehicleOption[]>([])
const drivers = ref<ReferenceOption[]>([])
const serviceProviders = ref<ReferenceOption[]>([])
const trips = ref<FuelSupportTripOption[]>([])
const fuelTypes = ref<string[]>([])

const form = ref<CreateFuelLogPayload>({
  vehicle_id: 0,
  driver_id: null,
  trip_id: null,
  service_provider_id: null,
  reference_number: '',
  fuel_type: 'diesel',
  quantity_liters: 0,
  cost_per_liter: 0,
  odometer_reading: 0,
  is_full_tank: true,
  fueled_at: new Date().toISOString().slice(0, 16),
  notes: '',
})

const selectedVehicle = computed(() => vehicles.value.find((vehicle) => vehicle.id === form.value.vehicle_id) ?? null)
const filteredTrips = computed(() => trips.value.filter((trip) => !form.value.vehicle_id || trip.vehicle_id === form.value.vehicle_id))
const estimatedTotal = computed(() => (Number(form.value.quantity_liters) * Number(form.value.cost_per_liter)).toFixed(2))

async function loadSupportData() {
  const data = await getResource<FuelLogSupportData>('/fuel-logs/support-data')
  vehicles.value = data.vehicles
  drivers.value = data.drivers
  serviceProviders.value = data.service_providers
  trips.value = data.trips
  fuelTypes.value = data.fuel_types

  if (!isEditMode.value) {
    if (!form.value.vehicle_id && vehicles.value[0]) {
      form.value.vehicle_id = vehicles.value[0].id
      form.value.fuel_type = vehicles.value[0].fuel_type
      form.value.odometer_reading = vehicles.value[0].odometer_reading
    }
  }
}

async function loadFuelLog() {
  if (!isEditMode.value) return

  const fuelLog = await getResource<FuelLog>(`/fuel-logs/${route.params.id}`)
  form.value = {
    vehicle_id: fuelLog.vehicle_id,
    driver_id: fuelLog.driver_id,
    trip_id: fuelLog.trip_id,
    service_provider_id: fuelLog.service_provider_id,
    reference_number: fuelLog.reference_number ?? '',
    fuel_type: fuelLog.fuel_type,
    quantity_liters: Number(fuelLog.quantity_liters),
    cost_per_liter: Number(fuelLog.cost_per_liter),
    odometer_reading: fuelLog.odometer_reading,
    is_full_tank: fuelLog.is_full_tank,
    fueled_at: fuelLog.fueled_at.slice(0, 16),
    notes: fuelLog.notes ?? '',
  }
}

async function submit() {
  submitting.value = true
  errorMessage.value = null
  fieldErrors.value = {}

  try {
    if (isEditMode.value) {
      await updateResource<FuelLog, UpdateFuelLogPayload>(`/fuel-logs/${route.params.id}`, form.value)
    } else {
      await createResource<FuelLog, CreateFuelLogPayload>('/fuel-logs', form.value)
    }

    await router.push({ name: 'fuel-logs' })
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

watch(selectedVehicle, (vehicle) => {
  if (!vehicle || isEditMode.value) return

  form.value.fuel_type = vehicle.fuel_type
  form.value.odometer_reading = vehicle.odometer_reading
})

watch(() => form.value.trip_id, (tripId) => {
  const trip = filteredTrips.value.find((item) => item.id === tripId)

  if (!trip || isEditMode.value) return

  form.value.driver_id = trip.driver_id
})

onMounted(async () => {
  loading.value = true
  try {
    await loadSupportData()
    await loadFuelLog()
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
      :title="isEditMode ? 'Edit fuel log' : 'Record fuel log'"
      description="Fuel logs tie together supplier transactions, odometer checkpoints, and vehicle consumption history."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'fuel-logs' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
        >
          Back to fuel logs
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to save fuel log"
      :description="errorMessage"
      tone="danger"
    />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.9fr]" @submit.prevent="submit">
      <SectionCard title="Fuel event" description="Capture the supplier, vehicle, and linked trip context for this fueling activity.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Vehicle</span>
            <select v-model="form.vehicle_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                {{ vehicle.label }}{{ vehicle.secondary ? ` · ${vehicle.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('vehicle_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Driver</span>
            <select v-model="form.driver_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option :value="null">No driver linked</option>
              <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                {{ driver.label }}{{ driver.secondary ? ` · ${driver.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('driver_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Trip</span>
            <select v-model="form.trip_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option :value="null">Not linked to a trip</option>
              <option v-for="trip in filteredTrips" :key="trip.id" :value="trip.id">
                {{ trip.label }}{{ trip.secondary ? ` · ${trip.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('trip_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Fuel station</span>
            <select v-model="form.service_provider_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option :value="null">No station linked</option>
              <option v-for="serviceProvider in serviceProviders" :key="serviceProvider.id" :value="serviceProvider.id">
                {{ serviceProvider.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('service_provider_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Reference number</span>
            <input v-model="form.reference_number" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('reference_number')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Fuel type</span>
            <select v-model="form.fuel_type" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option v-for="fuelType in fuelTypes" :key="fuelType" :value="fuelType">
                {{ fuelType.replaceAll('_', ' ') }}
              </option>
            </select>
            <FieldError :errors="errorsFor('fuel_type')" />
          </label>
        </div>
      </SectionCard>

      <SectionCard title="Metering and cost" description="Keep odometer and spend figures aligned with the fueling event.">
        <div class="space-y-4 text-sm text-slate-700 dark:text-slate-200">
          <label class="space-y-2">
            <span class="font-medium">Quantity (litres)</span>
            <input v-model.number="form.quantity_liters" type="number" min="0.01" step="0.01" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('quantity_liters')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Cost per litre</span>
            <input v-model.number="form.cost_per_liter" type="number" min="0" step="0.0001" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('cost_per_liter')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Odometer reading</span>
            <input v-model.number="form.odometer_reading" type="number" min="0" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('odometer_reading')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Fueled at</span>
            <input v-model="form.fueled_at" type="datetime-local" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('fueled_at')" />
          </label>
          <label class="flex items-center gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3">
            <input v-model="form.is_full_tank" type="checkbox" class="h-4 w-4 rounded border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 focus:ring-slate-400">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Marked as full tank</span>
          </label>
          <label class="space-y-2">
            <span class="font-medium">Notes</span>
            <textarea v-model="form.notes" class="min-h-24 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading" />
            <FieldError :errors="errorsFor('notes')" />
          </label>
          <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
            Estimated total: <span class="font-semibold">{{ estimatedTotal }}</span>
          </div>
          <div class="flex flex-col gap-3 sm:flex-row">
            <RouterLink :to="{ name: 'fuel-logs' }" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50 sm:w-auto">
              Cancel
            </RouterLink>
            <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading || submitting">
              {{ submitting ? 'Saving...' : isEditMode ? 'Save fuel log' : 'Record fuel log' }}
            </button>
          </div>
        </div>
      </SectionCard>
    </form>
  </div>
</template>
