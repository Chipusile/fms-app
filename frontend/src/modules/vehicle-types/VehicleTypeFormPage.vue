<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import { vehicleFuelTypeOptions } from '@/lib/fleet-options'
import { createResource, getResource, updateResource } from '@/lib/resource-client'
import type { ApiError, CreateVehicleTypePayload, UpdateVehicleTypePayload, VehicleType } from '@/types'

const route = useRoute()
const router = useRouter()
const isEditMode = computed(() => Boolean(route.params.id))
const loading = ref(false)
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})

const form = ref<CreateVehicleTypePayload>({
  name: '',
  code: '',
  description: '',
  default_fuel_type: null,
  default_service_interval_km: null,
  is_active: true,
})

async function loadVehicleType() {
  if (!isEditMode.value) {
    return
  }

  const vehicleType = await getResource<VehicleType>(`/vehicle-types/${route.params.id}`)
  form.value = {
    name: vehicleType.name,
    code: vehicleType.code,
    description: vehicleType.description ?? '',
    default_fuel_type: vehicleType.default_fuel_type,
    default_service_interval_km: vehicleType.default_service_interval_km,
    is_active: vehicleType.is_active,
  }
}

async function submit() {
  submitting.value = true
  errorMessage.value = null
  fieldErrors.value = {}

  try {
    if (isEditMode.value) {
      await updateResource<VehicleType, UpdateVehicleTypePayload>(`/vehicle-types/${route.params.id}`, form.value)
    } else {
      await createResource<VehicleType, CreateVehicleTypePayload>('/vehicle-types', form.value)
    }

    await router.push({ name: 'vehicle-types' })
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
    await loadVehicleType()
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
      eyebrow="Fleet"
      :title="isEditMode ? 'Edit vehicle type' : 'Create vehicle type'"
      description="Vehicle type catalogues standardise fleet registration defaults across the tenant."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'vehicle-types' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
        >
          Cancel
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to save vehicle type"
      :description="errorMessage"
      tone="danger"
    />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.9fr]" @submit.prevent="submit">
      <SectionCard title="Catalogue details" description="Core metadata and default asset behaviour.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Name</span>
            <input v-model="form.name" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('name')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Code</span>
            <input v-model="form.code" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 uppercase outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('code')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Default fuel type</span>
            <select v-model="form.default_fuel_type" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option :value="null">No default</option>
              <option v-for="option in vehicleFuelTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <FieldError :errors="errorsFor('default_fuel_type')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Default service interval (km)</span>
            <input v-model.number="form.default_service_interval_km" type="number" min="0" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('default_service_interval_km')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Description</span>
            <textarea v-model="form.description" class="min-h-32 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading" />
            <FieldError :errors="errorsFor('description')" />
          </label>
        </div>
      </SectionCard>

      <SectionCard title="Availability" description="Control whether this type remains selectable for new vehicles.">
        <div class="space-y-4 text-sm text-slate-700 dark:text-slate-200">
          <label class="flex items-center gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3">
            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 dark:border-slate-700" :disabled="loading">
            <span>Active and available for new fleet records</span>
          </label>
          <div class="flex flex-col gap-3 sm:flex-row">
            <RouterLink :to="{ name: 'vehicle-types' }" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50 sm:w-auto">
              Cancel
            </RouterLink>
            <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading || submitting">
              {{ submitting ? 'Saving...' : isEditMode ? 'Save vehicle type' : 'Create vehicle type' }}
            </button>
          </div>
        </div>
      </SectionCard>
    </form>
  </div>
</template>
