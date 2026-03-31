<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import { vehicleFuelTypeOptions, vehicleOwnershipOptions, vehicleStatusOptions, vehicleTransmissionOptions } from '@/lib/fleet-options'
import { createResource, getResource, listResource, updateResource } from '@/lib/resource-client'
import type { ApiError, CreateVehiclePayload, Department, UpdateVehiclePayload, Vehicle, VehicleType } from '@/types'

const route = useRoute()
const router = useRouter()
const isEditMode = computed(() => Boolean(route.params.id))
const loading = ref(false)
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const vehicleTypes = ref<VehicleType[]>([])
const departments = ref<Department[]>([])

const form = ref<CreateVehiclePayload>({
  vehicle_type_id: 0,
  department_id: null,
  registration_number: '',
  asset_tag: '',
  vin: '',
  make: '',
  model: '',
  year: new Date().getFullYear(),
  color: '',
  fuel_type: 'diesel',
  transmission_type: 'manual',
  ownership_type: 'owned',
  status: 'active',
  seating_capacity: null,
  tank_capacity_liters: null,
  odometer_reading: 0,
  acquisition_date: '',
  acquisition_cost: null,
  notes: '',
})

async function loadSupportData() {
  const [typeResponse, departmentResponse] = await Promise.all([
    listResource<VehicleType>('/vehicle-types', { per_page: 100 }),
    listResource<Department>('/departments', { per_page: 100 }),
  ])

  vehicleTypes.value = typeResponse.data
  departments.value = departmentResponse.data

  if (!isEditMode.value && !form.value.vehicle_type_id && vehicleTypes.value[0]) {
    form.value.vehicle_type_id = vehicleTypes.value[0].id
  }
}

async function loadVehicle() {
  if (!isEditMode.value) return

  const vehicle = await getResource<Vehicle>(`/vehicles/${route.params.id}`)
  form.value = {
    vehicle_type_id: vehicle.vehicle_type_id,
    department_id: vehicle.department_id,
    registration_number: vehicle.registration_number,
    asset_tag: vehicle.asset_tag ?? '',
    vin: vehicle.vin ?? '',
    make: vehicle.make,
    model: vehicle.model,
    year: vehicle.year,
    color: vehicle.color ?? '',
    fuel_type: vehicle.fuel_type,
    transmission_type: vehicle.transmission_type,
    ownership_type: vehicle.ownership_type,
    status: vehicle.status,
    seating_capacity: vehicle.seating_capacity,
    tank_capacity_liters: vehicle.tank_capacity_liters ? Number(vehicle.tank_capacity_liters) : null,
    odometer_reading: vehicle.odometer_reading,
    acquisition_date: vehicle.acquisition_date ?? '',
    acquisition_cost: vehicle.acquisition_cost ? Number(vehicle.acquisition_cost) : null,
    notes: vehicle.notes ?? '',
  }
}

async function submit() {
  submitting.value = true
  errorMessage.value = null
  fieldErrors.value = {}

  try {
    if (isEditMode.value) {
      await updateResource<Vehicle, UpdateVehiclePayload>(`/vehicles/${route.params.id}`, form.value)
    } else {
      await createResource<Vehicle, CreateVehiclePayload>('/vehicles', form.value)
    }

    await router.push({ name: 'vehicles' })
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
    await Promise.all([loadSupportData(), loadVehicle()])
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader eyebrow="Fleet" :title="isEditMode ? 'Edit vehicle' : 'Create vehicle'" description="Register and maintain tenant fleet assets with type, ownership, and lifecycle context.">
      <template #actions>
        <RouterLink :to="{ name: 'vehicles' }" class="inline-flex items-center rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
          Cancel
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert v-if="errorMessage" title="Unable to save vehicle" :description="errorMessage" tone="danger" />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.95fr]" @submit.prevent="submit">
      <SectionCard title="Identity" description="Core registration and classification details for the fleet asset.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Registration number</span>
            <input v-model="form.registration_number" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('registration_number')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Asset tag</span>
            <input v-model="form.asset_tag" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('asset_tag')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Vehicle type</span>
            <select v-model="form.vehicle_type_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option v-for="vehicleType in vehicleTypes" :key="vehicleType.id" :value="vehicleType.id">{{ vehicleType.name }}</option>
            </select>
            <FieldError :errors="errorsFor('vehicle_type_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Department</span>
            <select v-model="form.department_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option :value="null">Unassigned</option>
              <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
            </select>
            <FieldError :errors="errorsFor('department_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Make</span>
            <input v-model="form.make" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('make')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Model</span>
            <input v-model="form.model" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('model')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Year</span>
            <input v-model.number="form.year" type="number" min="1990" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('year')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">VIN</span>
            <input v-model="form.vin" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('vin')" />
          </label>
        </div>
      </SectionCard>

      <SectionCard title="Operational defaults" description="Lifecycle, fuel, and asset economics used by later workflows.">
        <div class="space-y-4 text-sm text-slate-700">
          <label class="space-y-2">
            <span class="font-medium">Fuel type</span>
            <select v-model="form.fuel_type" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option v-for="option in vehicleFuelTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <FieldError :errors="errorsFor('fuel_type')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Transmission</span>
            <select v-model="form.transmission_type" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option :value="null">Not specified</option>
              <option v-for="option in vehicleTransmissionOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <FieldError :errors="errorsFor('transmission_type')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Ownership</span>
            <select v-model="form.ownership_type" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option v-for="option in vehicleOwnershipOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <FieldError :errors="errorsFor('ownership_type')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Status</span>
            <select v-model="form.status" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option v-for="option in vehicleStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <FieldError :errors="errorsFor('status')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Odometer</span>
            <input v-model.number="form.odometer_reading" type="number" min="0" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('odometer_reading')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Tank capacity (litres)</span>
            <input v-model.number="form.tank_capacity_liters" type="number" min="0" step="0.01" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('tank_capacity_liters')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Acquisition date</span>
            <input v-model="form.acquisition_date" type="date" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('acquisition_date')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Acquisition cost</span>
            <input v-model.number="form.acquisition_cost" type="number" min="0" step="0.01" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('acquisition_cost')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Notes</span>
            <textarea v-model="form.notes" class="min-h-24 w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading" />
            <FieldError :errors="errorsFor('notes')" />
          </label>
          <div class="flex flex-col gap-3 sm:flex-row">
            <RouterLink :to="{ name: 'vehicles' }" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto">
              Cancel
            </RouterLink>
            <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading || submitting">
              {{ submitting ? 'Saving...' : isEditMode ? 'Save vehicle' : 'Create vehicle' }}
            </button>
          </div>
        </div>
      </SectionCard>
    </form>
  </div>
</template>
