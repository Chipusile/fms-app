<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import FieldError from '@/components/ui/FieldError.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import { currencyOptions, dateFormatOptions, timezoneOptions } from '@/lib/options'
import api from '@/plugins/axios'
import { getResource, listResource, updateResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type { ApiError, ApiResponse, Setting, Tenant, UpdateTenantPayload } from '@/types'

const auth = useAuthStore()
const settings = ref<Setting[]>([])
const loading = ref(false)
const submitting = ref(false)
const profileSubmitting = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const profileErrors = ref<Record<string, string[]>>({})

const timezone = ref('UTC')
const currency = ref('USD')
const dateFormat = ref('Y-m-d')
const complianceReminderDays = ref(30)
const maintenanceReminderDays = ref(7)
const maintenanceReminderKm = ref(500)
const componentReminderDays = ref(14)
const componentReminderKm = ref(1000)
const remindersEnabled = ref(true)
const tenantProfile = ref<UpdateTenantPayload>({
  name: '',
  email: '',
  phone: '',
  website: '',
  address: '',
  city: '',
  state: '',
  country: '',
  postal_code: '',
})

const tenantName = computed(() => auth.user?.tenant?.name ?? 'Current organisation')
const canManageSettings = computed(() => auth.hasPermission('settings.update'))
const canUpdateTenantProfile = computed(() => auth.hasPermission('tenants.update') && !!auth.tenantId)

function getSettingValue(key: string) {
  return settings.value.find((setting) => setting.key === key)?.value
}

async function loadSettings() {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<Setting>('/settings', { per_page: 100 })
    settings.value = response.data

    timezone.value = String(getSettingValue('timezone') ?? auth.user?.tenant?.timezone ?? 'UTC')
    currency.value = String(getSettingValue('currency') ?? auth.user?.tenant?.currency ?? 'USD')
    dateFormat.value = String(getSettingValue('date_format') ?? auth.user?.tenant?.date_format ?? 'Y-m-d')
    complianceReminderDays.value = Number(getSettingValue('compliance.reminder_days') ?? 30)
    maintenanceReminderDays.value = Number(getSettingValue('maintenance.reminder_days') ?? 7)
    maintenanceReminderKm.value = Number(getSettingValue('maintenance.reminder_km_buffer') ?? 500)
    componentReminderDays.value = Number(getSettingValue('component.reminder_days') ?? 14)
    componentReminderKm.value = Number(getSettingValue('component.reminder_km_buffer') ?? 1000)
    remindersEnabled.value = Boolean(getSettingValue('notifications.reminders.enabled') ?? true)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function loadTenantProfile() {
  if (!canUpdateTenantProfile.value || !auth.tenantId) {
    return
  }

  const tenant = await getResource<Tenant>(`/tenants/${auth.tenantId}`)
  tenantProfile.value = {
    name: tenant.name,
    email: tenant.email ?? '',
    phone: tenant.phone ?? '',
    website: tenant.website ?? '',
    address: tenant.address ?? '',
    city: tenant.city ?? '',
    state: tenant.state ?? '',
    country: tenant.country ?? '',
    postal_code: tenant.postal_code ?? '',
  }
}

async function saveSettings() {
  if (!canManageSettings.value) {
    return
  }

  submitting.value = true
  errorMessage.value = null
  successMessage.value = null

  try {
    const payload = {
      settings: [
        { group: 'general', key: 'timezone', value: timezone.value },
        { group: 'general', key: 'currency', value: currency.value },
        { group: 'general', key: 'date_format', value: dateFormat.value },
        { group: 'notifications', key: 'compliance.reminder_days', value: complianceReminderDays.value },
        { group: 'notifications', key: 'notifications.reminders.enabled', value: remindersEnabled.value },
        { group: 'maintenance', key: 'maintenance.reminder_days', value: maintenanceReminderDays.value },
        { group: 'maintenance', key: 'maintenance.reminder_km_buffer', value: maintenanceReminderKm.value },
        { group: 'maintenance', key: 'component.reminder_days', value: componentReminderDays.value },
        { group: 'maintenance', key: 'component.reminder_km_buffer', value: componentReminderKm.value },
      ],
    }

    const response = await api.put<ApiResponse<null>>('/settings', payload)
    successMessage.value = response.data.message
    await loadSettings()
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    submitting.value = false
  }
}

async function saveTenantProfile() {
  if (!canUpdateTenantProfile.value || !auth.tenantId) {
    return
  }

  profileSubmitting.value = true
  errorMessage.value = null
  successMessage.value = null
  profileErrors.value = {}

  try {
    await updateResource<Tenant, UpdateTenantPayload>(`/tenants/${auth.tenantId}`, tenantProfile.value)
    successMessage.value = 'Organisation profile updated successfully.'
    await auth.fetchUser()
    await loadTenantProfile()
  } catch (error) {
    const apiError = error as ApiError
    errorMessage.value = apiError.message
    profileErrors.value = apiError.errors ?? {}
  } finally {
    profileSubmitting.value = false
  }
}

function profileFieldErrors(field: string): string[] | undefined {
  return profileErrors.value[field]
}

onMounted(async () => {
  await Promise.all([loadSettings(), loadTenantProfile()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Configuration"
      title="Settings"
      :description="`Manage baseline tenant configuration for ${tenantName}. Grouped settings keep workflow rules configurable instead of hardcoded.`"
    />

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load or save settings"
      :description="errorMessage"
      tone="danger"
    />

    <InlineAlert
      v-if="successMessage"
      title="Settings updated"
      :description="successMessage"
      tone="success"
    />

    <InlineAlert
      v-if="!canManageSettings"
      title="Read-only settings"
      description="You can review tenant defaults and reminder rules here, but you need settings.update permission to save changes."
      tone="info"
    />

    <div class="grid gap-6 xl:grid-cols-2">
      <SectionCard
        v-if="canUpdateTenantProfile"
        title="Organisation profile"
        description="Tenant administrators can manage their own company profile without needing platform-level access."
      >
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Organisation name</span>
            <input v-model="tenantProfile.name" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || profileSubmitting">
            <FieldError :errors="profileFieldErrors('name')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Email</span>
            <input v-model="tenantProfile.email" type="email" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || profileSubmitting">
            <FieldError :errors="profileFieldErrors('email')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Phone</span>
            <input v-model="tenantProfile.phone" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || profileSubmitting">
            <FieldError :errors="profileFieldErrors('phone')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Website</span>
            <input v-model="tenantProfile.website" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || profileSubmitting">
            <FieldError :errors="profileFieldErrors('website')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 md:col-span-2">
            <span class="font-medium">Address</span>
            <input v-model="tenantProfile.address" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || profileSubmitting">
            <FieldError :errors="profileFieldErrors('address')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">City</span>
            <input v-model="tenantProfile.city" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || profileSubmitting">
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">State / province</span>
            <input v-model="tenantProfile.state" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || profileSubmitting">
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Postal code</span>
            <input v-model="tenantProfile.postal_code" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || profileSubmitting">
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Country</span>
            <input v-model="tenantProfile.country" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || profileSubmitting">
          </label>
          <button
            type="button"
            class="md:col-span-2 w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="loading || profileSubmitting"
            @click="saveTenantProfile"
          >
            {{ profileSubmitting ? 'Saving...' : 'Save organisation profile' }}
          </button>
        </div>
      </SectionCard>

      <SectionCard title="Organisation defaults" description="Tenant identity and operational defaults.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Timezone</span>
            <select
              v-model="timezone"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || !canManageSettings"
            >
              <option
                v-for="option in timezoneOptions"
                :key="option"
                :value="option"
              >
                {{ option }}
              </option>
            </select>
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Currency</span>
            <select
              v-model="currency"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || !canManageSettings"
            >
              <option
                v-for="option in currencyOptions"
                :key="option"
                :value="option"
              >
                {{ option }}
              </option>
            </select>
          </label>
          <label class="space-y-2 text-sm text-slate-700 md:col-span-2">
            <span class="font-medium">Date format</span>
            <select
              v-model="dateFormat"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || !canManageSettings"
            >
              <option
                v-for="option in dateFormatOptions"
                :key="option"
                :value="option"
              >
                {{ option }}
              </option>
            </select>
          </label>
        </div>
      </SectionCard>

      <SectionCard title="Reminder rules" description="These values are stored as tenant settings so reminder behaviour stays configurable.">
        <div class="space-y-4 text-sm text-slate-700">
          <label class="space-y-2">
            <span class="font-medium">Compliance reminder lead time (days)</span>
            <input
              v-model.number="complianceReminderDays"
              type="number"
              min="1"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || !canManageSettings"
            >
          </label>
          <label class="space-y-2">
            <span class="font-medium">Reminder automation</span>
            <select
              v-model="remindersEnabled"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || !canManageSettings"
            >
              <option :value="true">Enabled</option>
              <option :value="false">Disabled</option>
            </select>
          </label>
          <label class="space-y-2">
            <span class="font-medium">Maintenance reminder lead time (days)</span>
            <input
              v-model.number="maintenanceReminderDays"
              type="number"
              min="1"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || !canManageSettings"
            >
          </label>
          <label class="space-y-2">
            <span class="font-medium">Maintenance warning buffer (km)</span>
            <input
              v-model.number="maintenanceReminderKm"
              type="number"
              min="1"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || !canManageSettings"
            >
          </label>
          <label class="space-y-2">
            <span class="font-medium">Component replacement lead time (days)</span>
            <input
              v-model.number="componentReminderDays"
              type="number"
              min="1"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || !canManageSettings"
            >
          </label>
          <label class="space-y-2">
            <span class="font-medium">Component warning buffer (km)</span>
            <input
              v-model.number="componentReminderKm"
              type="number"
              min="1"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || !canManageSettings"
            >
          </label>
          <button
            v-if="canManageSettings"
            type="button"
            class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="loading || submitting"
            @click="saveSettings"
          >
            {{ submitting ? 'Saving...' : 'Save settings' }}
          </button>
        </div>
      </SectionCard>
    </div>
  </div>
</template>
