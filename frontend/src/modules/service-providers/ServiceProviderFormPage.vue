<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import { serviceProviderStatusOptions, serviceProviderTypeOptions } from '@/lib/fleet-options'
import { createResource, getResource, updateResource } from '@/lib/resource-client'
import type { ApiError, CreateServiceProviderPayload, ServiceProvider, UpdateServiceProviderPayload } from '@/types'

const route = useRoute()
const router = useRouter()
const isEditMode = computed(() => Boolean(route.params.id))
const loading = ref(false)
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})

const form = ref<CreateServiceProviderPayload>({
  name: '',
  provider_type: 'garage',
  contact_person: '',
  phone: '',
  email: '',
  website: '',
  address: '',
  tax_number: '',
  status: 'active',
  notes: '',
})

async function loadServiceProvider() {
  if (!isEditMode.value) return

  const provider = await getResource<ServiceProvider>(`/service-providers/${route.params.id}`)
  form.value = {
    name: provider.name,
    provider_type: provider.provider_type,
    contact_person: provider.contact_person ?? '',
    phone: provider.phone ?? '',
    email: provider.email ?? '',
    website: provider.website ?? '',
    address: provider.address ?? '',
    tax_number: provider.tax_number ?? '',
    status: provider.status,
    notes: provider.notes ?? '',
  }
}

async function submit() {
  submitting.value = true
  errorMessage.value = null
  fieldErrors.value = {}

  try {
    if (isEditMode.value) {
      await updateResource<ServiceProvider, UpdateServiceProviderPayload>(`/service-providers/${route.params.id}`, form.value)
    } else {
      await createResource<ServiceProvider, CreateServiceProviderPayload>('/service-providers', form.value)
    }

    await router.push({ name: 'service-providers' })
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
    await loadServiceProvider()
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader eyebrow="Partners" :title="isEditMode ? 'Edit service provider' : 'Create service provider'" description="Centralise external garages, insurers, and support vendors for later operational workflows.">
      <template #actions>
        <RouterLink :to="{ name: 'service-providers' }" class="inline-flex items-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50">
          Cancel
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert v-if="errorMessage" title="Unable to save service provider" :description="errorMessage" tone="danger" />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.9fr]" @submit.prevent="submit">
      <SectionCard title="Provider details" description="Identity and contact details for external partners.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Name</span>
            <input v-model="form.name" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('name')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Provider type</span>
            <select v-model="form.provider_type" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option v-for="option in serviceProviderTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <FieldError :errors="errorsFor('provider_type')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Contact person</span>
            <input v-model="form.contact_person" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('contact_person')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Phone</span>
            <input v-model="form.phone" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('phone')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Email</span>
            <input v-model="form.email" type="email" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('email')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Website</span>
            <input v-model="form.website" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('website')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Address</span>
            <input v-model="form.address" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('address')" />
          </label>
        </div>
      </SectionCard>

      <SectionCard title="Commercial details" description="Administrative metadata for compliance and procurement teams.">
        <div class="space-y-4 text-sm text-slate-700 dark:text-slate-200">
          <label class="space-y-2">
            <span class="font-medium">Tax number</span>
            <input v-model="form.tax_number" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('tax_number')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Status</span>
            <select v-model="form.status" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option v-for="option in serviceProviderStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <FieldError :errors="errorsFor('status')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Notes</span>
            <textarea v-model="form.notes" class="min-h-32 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading" />
            <FieldError :errors="errorsFor('notes')" />
          </label>
          <div class="flex flex-col gap-3 sm:flex-row">
            <RouterLink :to="{ name: 'service-providers' }" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50 sm:w-auto">
              Cancel
            </RouterLink>
            <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading || submitting">
              {{ submitting ? 'Saving...' : isEditMode ? 'Save provider' : 'Create provider' }}
            </button>
          </div>
        </div>
      </SectionCard>
    </form>
  </div>
</template>
