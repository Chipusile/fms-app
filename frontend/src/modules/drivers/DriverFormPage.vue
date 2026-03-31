<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import { driverStatusOptions } from '@/lib/fleet-options'
import { createResource, getResource, listResource, updateResource } from '@/lib/resource-client'
import type { ApiError, CreateDriverPayload, Department, Driver, UpdateDriverPayload } from '@/types'

const route = useRoute()
const router = useRouter()
const isEditMode = computed(() => Boolean(route.params.id))
const loading = ref(false)
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const departments = ref<Department[]>([])

const form = ref<CreateDriverPayload>({
  department_id: null,
  name: '',
  employee_number: '',
  license_number: '',
  license_class: '',
  license_expiry_date: '',
  phone: '',
  email: '',
  hire_date: '',
  status: 'active',
  notes: '',
})

async function loadDepartments() {
  try {
    const response = await listResource<Department>('/departments', { per_page: 100 })
    departments.value = response.data
  } catch {
    departments.value = []
  }
}

async function loadDriver() {
  if (!isEditMode.value) return

  const driver = await getResource<Driver>(`/drivers/${route.params.id}`)
  form.value = {
    department_id: driver.department_id,
    name: driver.name,
    employee_number: driver.employee_number ?? '',
    license_number: driver.license_number,
    license_class: driver.license_class ?? '',
    license_expiry_date: driver.license_expiry_date ?? '',
    phone: driver.phone ?? '',
    email: driver.email ?? '',
    hire_date: driver.hire_date ?? '',
    status: driver.status,
    notes: driver.notes ?? '',
  }
}

async function submit() {
  submitting.value = true
  errorMessage.value = null
  fieldErrors.value = {}

  try {
    if (isEditMode.value) {
      await updateResource<Driver, UpdateDriverPayload>(`/drivers/${route.params.id}`, form.value)
    } else {
      await createResource<Driver, CreateDriverPayload>('/drivers', form.value)
    }

    await router.push({ name: 'drivers' })
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
    await Promise.all([loadDepartments(), loadDriver()])
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader eyebrow="Operations" :title="isEditMode ? 'Edit driver' : 'Create driver'" description="Driver records can be managed independently from full application user accounts.">
      <template #actions>
        <RouterLink :to="{ name: 'drivers' }" class="inline-flex items-center rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
          Cancel
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert v-if="errorMessage" title="Unable to save driver" :description="errorMessage" tone="danger" />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.9fr]" @submit.prevent="submit">
      <SectionCard title="Driver details" description="Identity, licensing, and contact information.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Name</span>
            <input v-model="form.name" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('name')" />
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
            <span class="font-medium">Employee number</span>
            <input v-model="form.employee_number" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('employee_number')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">License number</span>
            <input v-model="form.license_number" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('license_number')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">License class</span>
            <input v-model="form.license_class" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('license_class')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">License expiry</span>
            <input v-model="form.license_expiry_date" type="date" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('license_expiry_date')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Phone</span>
            <input v-model="form.phone" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('phone')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Email</span>
            <input v-model="form.email" type="email" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('email')" />
          </label>
        </div>
      </SectionCard>

      <SectionCard title="Lifecycle" description="Operational status and employment timing.">
        <div class="space-y-4 text-sm text-slate-700">
          <label class="space-y-2">
            <span class="font-medium">Hire date</span>
            <input v-model="form.hire_date" type="date" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('hire_date')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Status</span>
            <select v-model="form.status" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option v-for="option in driverStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <FieldError :errors="errorsFor('status')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Notes</span>
            <textarea v-model="form.notes" class="min-h-32 w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading" />
            <FieldError :errors="errorsFor('notes')" />
          </label>
          <div class="flex flex-col gap-3 sm:flex-row">
            <RouterLink :to="{ name: 'drivers' }" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto">
              Cancel
            </RouterLink>
            <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading || submitting">
              {{ submitting ? 'Saving...' : isEditMode ? 'Save driver' : 'Create driver' }}
            </button>
          </div>
        </div>
      </SectionCard>
    </form>
  </div>
</template>
