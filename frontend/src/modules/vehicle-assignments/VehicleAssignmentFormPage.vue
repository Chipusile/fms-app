<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import { vehicleAssignmentStatusOptions, vehicleAssignmentTypeOptions } from '@/lib/fleet-options'
import { createResource, getResource, updateResource } from '@/lib/resource-client'
import type {
  ApiError,
  CreateVehicleAssignmentPayload,
  ReferenceOption,
  UpdateVehicleAssignmentPayload,
  VehicleAssignment,
  VehicleAssignmentSupportData,
} from '@/types'

const route = useRoute()
const router = useRouter()
const isEditMode = computed(() => Boolean(route.params.id))
const loading = ref(false)
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const vehicles = ref<ReferenceOption[]>([])
const drivers = ref<ReferenceOption[]>([])
const departments = ref<ReferenceOption[]>([])

const form = ref<CreateVehicleAssignmentPayload>({
  vehicle_id: 0,
  driver_id: null,
  department_id: null,
  assignment_type: 'driver',
  status: 'active',
  assigned_from: new Date().toISOString().slice(0, 10),
  assigned_to: null,
  notes: '',
})

async function loadSupportData() {
  const data = await getResource<VehicleAssignmentSupportData>('/vehicle-assignments/support-data')
  vehicles.value = data.vehicles
  drivers.value = data.drivers
  departments.value = data.departments

  if (!isEditMode.value && !form.value.vehicle_id && vehicles.value[0]) {
    form.value.vehicle_id = vehicles.value[0].id
  }
}

async function loadAssignment() {
  if (!isEditMode.value) {
    return
  }

  const assignment = await getResource<VehicleAssignment>(`/vehicle-assignments/${route.params.id}`)
  form.value = {
    vehicle_id: assignment.vehicle_id,
    driver_id: assignment.driver_id,
    department_id: assignment.department_id,
    assignment_type: assignment.assignment_type,
    status: assignment.status,
    assigned_from: assignment.assigned_from,
    assigned_to: assignment.assigned_to,
    notes: assignment.notes ?? '',
  }
}

async function submit() {
  submitting.value = true
  errorMessage.value = null
  fieldErrors.value = {}

  try {
    if (isEditMode.value) {
      await updateResource<VehicleAssignment, UpdateVehicleAssignmentPayload>(`/vehicle-assignments/${route.params.id}`, form.value)
    } else {
      await createResource<VehicleAssignment, CreateVehicleAssignmentPayload>('/vehicle-assignments', form.value)
    }

    await router.push({ name: 'vehicle-assignments' })
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
    await Promise.all([loadSupportData(), loadAssignment()])
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
      eyebrow="Allocations"
      :title="isEditMode ? 'Edit vehicle assignment' : 'Create vehicle assignment'"
      description="Assignments keep an auditable record of who controls a vehicle, when it started, and when it ended."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'vehicle-assignments' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
        >
          Cancel
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to save assignment"
      :description="errorMessage"
      tone="danger"
    />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.9fr]" @submit.prevent="submit">
      <SectionCard title="Assignment target" description="Choose the vehicle and the operational owner for this record.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Vehicle</span>
            <select v-model="form.vehicle_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                {{ vehicle.label }}{{ vehicle.secondary ? ` · ${vehicle.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('vehicle_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Assignment type</span>
            <select v-model="form.assignment_type" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option v-for="option in vehicleAssignmentTypeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('assignment_type')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Status</span>
            <select v-model="form.status" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option v-for="option in vehicleAssignmentStatusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('status')" />
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
            <span class="font-medium">Department</span>
            <select v-model="form.department_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option :value="null">No department linked</option>
              <option v-for="department in departments" :key="department.id" :value="department.id">
                {{ department.label }}{{ department.secondary ? ` · ${department.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('department_id')" />
          </label>
        </div>
      </SectionCard>

      <SectionCard title="Timeline" description="Active assignments must not overlap for the same vehicle. End the prior record before creating the next active one.">
        <div class="space-y-4 text-sm text-slate-700 dark:text-slate-200">
          <label class="space-y-2">
            <span class="font-medium">Assigned from</span>
            <input v-model="form.assigned_from" type="date" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('assigned_from')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Assigned to</span>
            <input v-model="form.assigned_to" type="date" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('assigned_to')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Notes</span>
            <textarea v-model="form.notes" class="min-h-28 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading" />
            <FieldError :errors="errorsFor('notes')" />
          </label>
          <p class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-xs leading-5 text-slate-600 dark:text-slate-400">
            At least one assignment target is required. Use a driver for direct accountability, a department for cost ownership, or both when the operational workflow needs both references.
          </p>
          <div class="flex flex-col gap-3 sm:flex-row">
            <RouterLink :to="{ name: 'vehicle-assignments' }" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50 sm:w-auto">
              Cancel
            </RouterLink>
            <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading || submitting">
              {{ submitting ? 'Saving...' : isEditMode ? 'Save assignment' : 'Create assignment' }}
            </button>
          </div>
        </div>
      </SectionCard>
    </form>
  </div>
</template>
