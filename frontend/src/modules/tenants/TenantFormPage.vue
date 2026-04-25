<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import { currencyOptions, dateFormatOptions, tenantStatusOptions, timezoneOptions } from '@/lib/options'
import { createResource, getResource, updateResource } from '@/lib/resource-client'
import type { ApiError, CreateTenantPayload, Tenant, TenantStatus, UpdateTenantPayload } from '@/types'

const route = useRoute()
const router = useRouter()
const isEditMode = computed(() => Boolean(route.params.id))
const loading = ref(false)
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})

const form = ref<CreateTenantPayload>({
  name: '',
  slug: '',
  domain: '',
  status: 'active',
  address: '',
  city: '',
  state: '',
  country: '',
  postal_code: '',
  phone: '',
  email: '',
  website: '',
  timezone: 'UTC',
  currency: 'USD',
  date_format: 'Y-m-d',
})

async function loadTenant() {
  if (!isEditMode.value) {
    return
  }

  const tenant = await getResource<Tenant>(`/tenants/${route.params.id}`)
  form.value = {
    name: tenant.name,
    slug: tenant.slug,
    domain: tenant.domain ?? '',
    status: tenant.status,
    address: tenant.address ?? '',
    city: tenant.city ?? '',
    state: tenant.state ?? '',
    country: tenant.country ?? '',
    postal_code: tenant.postal_code ?? '',
    phone: tenant.phone ?? '',
    email: tenant.email ?? '',
    website: tenant.website ?? '',
    timezone: tenant.timezone,
    currency: tenant.currency,
    date_format: tenant.date_format,
  }
}

async function submit() {
  submitting.value = true
  errorMessage.value = null
  fieldErrors.value = {}

  try {
    if (isEditMode.value) {
      await updateResource<Tenant, UpdateTenantPayload>(`/tenants/${route.params.id}`, form.value)
    } else {
      await createResource<Tenant, CreateTenantPayload>('/tenants', form.value)
    }

    await router.push({ name: 'tenants' })
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
    await loadTenant()
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
      eyebrow="Platform"
      :title="isEditMode ? 'Edit tenant' : 'Create tenant'"
      description="Platform operators manage organisation onboarding, branding defaults, and operating context from here."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'tenants' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
        >
          Cancel
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to save tenant"
      :description="errorMessage"
      tone="danger"
    />

    <form
      class="grid gap-6 xl:grid-cols-[1fr_1fr]"
      @submit.prevent="submit"
    >
      <SectionCard title="Organisation profile" description="Core tenant identity and contact information.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Name</span>
            <input v-model="form.name" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('name')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Slug</span>
            <input v-model="form.slug" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('slug')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Domain</span>
            <input v-model="form.domain" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('domain')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Status</span>
            <select v-model="form.status" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option
                v-for="option in tenantStatusOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('status')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Email</span>
            <input v-model="form.email" type="email" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('email')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Phone</span>
            <input v-model="form.phone" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('phone')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Website</span>
            <input v-model="form.website" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('website')" />
          </label>
        </div>
      </SectionCard>

      <SectionCard title="Operational defaults" description="Locale, address, and date/currency conventions.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Address</span>
            <input v-model="form.address" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('address')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">City</span>
            <input v-model="form.city" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">State</span>
            <input v-model="form.state" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Country</span>
            <input v-model="form.country" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Postal code</span>
            <input v-model="form.postal_code" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Timezone</span>
            <select v-model="form.timezone" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option
                v-for="option in timezoneOptions"
                :key="option"
                :value="option"
              >
                {{ option }}
              </option>
            </select>
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Currency</span>
            <select v-model="form.currency" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option
                v-for="option in currencyOptions"
                :key="option"
                :value="option"
              >
                {{ option }}
              </option>
            </select>
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Date format</span>
            <select v-model="form.date_format" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option
                v-for="option in dateFormatOptions"
                :key="option"
                :value="option"
              >
                {{ option }}
              </option>
            </select>
          </label>
          <div class="md:col-span-2 flex flex-col gap-3 sm:flex-row">
            <RouterLink
              :to="{ name: 'tenants' }"
              class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50 sm:w-auto"
            >
              Cancel
            </RouterLink>
            <button
              type="submit"
              class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="loading || submitting"
            >
              {{ submitting ? 'Saving...' : isEditMode ? 'Save tenant' : 'Create tenant' }}
            </button>
          </div>
        </div>
      </SectionCard>
    </form>
  </div>
</template>
