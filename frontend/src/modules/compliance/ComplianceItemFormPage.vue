<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { complianceCategoryOptions, compliantTypeOptions } from '@/lib/fleet-options'
import { createResource, getResource, updateResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  ComplianceItem,
  ComplianceItemPayload,
  ComplianceSupportData,
  ReferenceOption,
} from '@/types'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const isEditMode = computed(() => Boolean(route.params.id))
const canEdit = computed(() => auth.hasPermission('compliance.update'))
const canCreate = computed(() => auth.hasPermission('compliance.create'))
const loading = ref(false)
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const vehicles = ref<ReferenceOption[]>([])
const drivers = ref<ReferenceOption[]>([])
const complianceItem = ref<ComplianceItem | null>(null)

const form = ref<ComplianceItemPayload>({
  compliant_type: 'vehicle',
  compliant_id: 0,
  title: '',
  category: 'insurance',
  reference_number: '',
  issuer: '',
  issue_date: null,
  expiry_date: null,
  reminder_days: null,
  notes: '',
})

const entityOptions = computed(() => form.value.compliant_type === 'vehicle' ? vehicles.value : drivers.value)

function toNullableNumber(value: number | null | undefined): number | null {
  return value === null || value === undefined || Number.isNaN(Number(value)) ? null : Number(value)
}

function buildPayload(): ComplianceItemPayload {
  return {
    compliant_type: form.value.compliant_type,
    compliant_id: Number(form.value.compliant_id),
    title: form.value.title,
    category: form.value.category,
    reference_number: form.value.reference_number || null,
    issuer: form.value.issuer || null,
    issue_date: form.value.issue_date || null,
    expiry_date: form.value.expiry_date || null,
    reminder_days: toNullableNumber(form.value.reminder_days),
    notes: form.value.notes || null,
  }
}

async function loadSupportData() {
  const data = await getResource<ComplianceSupportData>('/compliance-items/support-data')
  vehicles.value = data.vehicles
  drivers.value = data.drivers

  if (!isEditMode.value && !form.value.compliant_id && entityOptions.value[0]) {
    form.value.compliant_id = entityOptions.value[0].id
  }
}

async function loadComplianceItem() {
  if (!isEditMode.value) return

  const record = await getResource<ComplianceItem>(`/compliance-items/${route.params.id}`)
  complianceItem.value = record
  form.value = {
    compliant_type: record.compliant_type ?? 'vehicle',
    compliant_id: record.compliant_id,
    title: record.title,
    category: record.category,
    reference_number: record.reference_number ?? '',
    issuer: record.issuer ?? '',
    issue_date: record.issue_date,
    expiry_date: record.expiry_date,
    reminder_days: record.reminder_days,
    notes: record.notes ?? '',
  }
}

async function submit() {
  if (isEditMode.value && !canEdit.value) return
  if (!isEditMode.value && !canCreate.value) return

  submitting.value = true
  errorMessage.value = null
  successMessage.value = null
  fieldErrors.value = {}

  try {
    const payload = buildPayload()

    if (isEditMode.value) {
      complianceItem.value = await updateResource<ComplianceItem, ComplianceItemPayload>(`/compliance-items/${route.params.id}`, payload)
      successMessage.value = 'Compliance item updated successfully.'
    } else {
      await createResource<ComplianceItem, ComplianceItemPayload>('/compliance-items', payload)
      await router.push({ name: 'compliance' })
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

function errorsFor(field: string): string[] | undefined {
  return fieldErrors.value[field]
}

watch(() => form.value.compliant_type, () => {
  const firstOption = entityOptions.value[0]

  if (!entityOptions.value.some((option) => option.id === form.value.compliant_id) && firstOption) {
    form.value.compliant_id = firstOption.id
  }
})

onMounted(async () => {
  loading.value = true

  try {
    await loadSupportData()
    await loadComplianceItem()
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
      eyebrow="Compliance"
      :title="isEditMode ? 'Manage compliance item' : 'Create compliance item'"
      description="Compliance records stay tenant-configurable and reusable across organisations without embedding policy logic in code."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'compliance' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
        >
          Back to compliance
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="successMessage"
      title="Compliance item updated"
      :description="successMessage"
      tone="success"
    />

    <InlineAlert
      v-if="errorMessage"
      title="Unable to save compliance item"
      :description="errorMessage"
      tone="danger"
    />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.9fr]" @submit.prevent="submit">
      <SectionCard title="Compliance details" description="Define the regulatory record, linked entity, and renewal timing.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Entity type</span>
            <select v-model="form.compliant_type" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
              <option v-for="option in compliantTypeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('compliant_type')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Entity</span>
            <select v-model="form.compliant_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
              <option v-for="option in entityOptions" :key="option.id" :value="option.id">
                {{ option.label }}{{ option.secondary ? ` · ${option.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('compliant_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 md:col-span-2">
            <span class="font-medium">Title</span>
            <input v-model="form.title" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('title')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Category</span>
            <select v-model="form.category" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
              <option v-for="option in complianceCategoryOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('category')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Reference number</span>
            <input v-model="form.reference_number" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('reference_number')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Issuer</span>
            <input v-model="form.issuer" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('issuer')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Reminder days</span>
            <input v-model.number="form.reminder_days" type="number" min="1" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('reminder_days')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Issue date</span>
            <input v-model="form.issue_date" type="date" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('issue_date')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Expiry date</span>
            <input v-model="form.expiry_date" type="date" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)">
            <FieldError :errors="errorsFor('expiry_date')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 md:col-span-2">
            <span class="font-medium">Notes</span>
            <textarea v-model="form.notes" class="min-h-28 w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading || (isEditMode && !canEdit)" />
            <FieldError :errors="errorsFor('notes')" />
          </label>
        </div>
      </SectionCard>

      <div class="space-y-6">
        <SectionCard title="Renewal status" description="Review the current compliance posture while updating the record.">
          <div class="space-y-4 text-sm text-slate-700">
            <div v-if="complianceItem" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Current status</p>
                  <p class="mt-1 text-base font-semibold text-slate-900">{{ complianceItem.title }}</p>
                </div>
                <StatusBadge :value="complianceItem.status" />
              </div>
              <p class="mt-3 text-sm text-slate-600">
                Expiry: {{ complianceItem.expiry_date ?? 'No expiry date' }}
                <span v-if="complianceItem.days_until_expiry !== null">
                  · {{ complianceItem.days_until_expiry >= 0 ? `${complianceItem.days_until_expiry} days left` : `${Math.abs(complianceItem.days_until_expiry)} days overdue` }}
                </span>
              </p>
            </div>
            <p v-else class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-600">
              New records compute their lifecycle status automatically from the configured expiry and reminder window.
            </p>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
              <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Linked entity</p>
              <p class="mt-1 text-base font-semibold text-slate-900">
                {{ entityOptions.find((option) => option.id === form.compliant_id)?.label ?? 'No entity selected' }}
              </p>
              <p class="mt-1 text-sm text-slate-600">
                {{ entityOptions.find((option) => option.id === form.compliant_id)?.secondary ?? 'Select the driver or vehicle that owns this compliance obligation.' }}
              </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
              <RouterLink :to="{ name: 'compliance' }" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto">
                Cancel
              </RouterLink>
              <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading || submitting || (isEditMode ? !canEdit : !canCreate)">
                {{ submitting ? 'Saving...' : isEditMode ? 'Save compliance item' : 'Create compliance item' }}
              </button>
            </div>
          </div>
        </SectionCard>
      </div>
    </form>
  </div>
</template>
