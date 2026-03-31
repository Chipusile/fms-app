<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import { departmentStatusOptions } from '@/lib/fleet-options'
import { createResource, getResource, updateResource } from '@/lib/resource-client'
import type { ApiError, CreateDepartmentPayload, Department, UpdateDepartmentPayload } from '@/types'

const route = useRoute()
const router = useRouter()
const isEditMode = computed(() => Boolean(route.params.id))
const loading = ref(false)
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})

const form = ref<CreateDepartmentPayload>({
  name: '',
  code: '',
  description: '',
  status: 'active',
})

async function loadDepartment() {
  if (!isEditMode.value) return

  const department = await getResource<Department>(`/departments/${route.params.id}`)
  form.value = {
    name: department.name,
    code: department.code,
    description: department.description ?? '',
    status: department.status,
  }
}

async function submit() {
  submitting.value = true
  errorMessage.value = null
  fieldErrors.value = {}

  try {
    if (isEditMode.value) {
      await updateResource<Department, UpdateDepartmentPayload>(`/departments/${route.params.id}`, form.value)
    } else {
      await createResource<Department, CreateDepartmentPayload>('/departments', form.value)
    }

    await router.push({ name: 'departments' })
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
    await loadDepartment()
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader eyebrow="Master Data" :title="isEditMode ? 'Edit department' : 'Create department'" description="Departments and cost centres define asset ownership and reporting boundaries.">
      <template #actions>
        <RouterLink :to="{ name: 'departments' }" class="inline-flex items-center rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
          Cancel
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert v-if="errorMessage" title="Unable to save department" :description="errorMessage" tone="danger" />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.8fr]" @submit.prevent="submit">
      <SectionCard title="Department details" description="Core metadata used across allocations and reporting.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Name</span>
            <input v-model="form.name" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('name')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Code</span>
            <input v-model="form.code" class="w-full rounded-2xl border border-slate-300 px-4 py-3 uppercase outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('code')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 md:col-span-2">
            <span class="font-medium">Description</span>
            <textarea v-model="form.description" class="min-h-32 w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading" />
            <FieldError :errors="errorsFor('description')" />
          </label>
        </div>
      </SectionCard>

      <SectionCard title="Lifecycle" description="Inactive departments remain visible for historical reporting but should not receive new allocations.">
        <div class="space-y-4 text-sm text-slate-700">
          <label class="space-y-2">
            <span class="font-medium">Status</span>
            <select v-model="form.status" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option v-for="option in departmentStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <FieldError :errors="errorsFor('status')" />
          </label>
          <div class="flex flex-col gap-3 sm:flex-row">
            <RouterLink :to="{ name: 'departments' }" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto">
              Cancel
            </RouterLink>
            <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading || submitting">
              {{ submitting ? 'Saving...' : isEditMode ? 'Save department' : 'Create department' }}
            </button>
          </div>
        </div>
      </SectionCard>
    </form>
  </div>
</template>
