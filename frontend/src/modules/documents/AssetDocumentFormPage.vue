<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import {
  assetDocumentStatusOptions,
  assetDocumentTypeOptions,
  documentableTypeOptions,
} from '@/lib/fleet-options'
import { getResource } from '@/lib/resource-client'
import api from '@/plugins/axios'
import type {
  ApiError,
  ApiResponse,
  AssetDocument,
  AssetDocumentFormPayload,
  AssetDocumentSupportData,
  DocumentableType,
  ReferenceOption,
} from '@/types'

const route = useRoute()
const router = useRouter()
const isEditMode = computed(() => Boolean(route.params.id))
const loading = ref(false)
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const supportData = ref<AssetDocumentSupportData>({
  vehicles: [],
  drivers: [],
  service_providers: [],
})
const existingDocument = ref<AssetDocument | null>(null)

const form = ref<AssetDocumentFormPayload>({
  documentable_type: 'vehicle',
  documentable_id: null,
  name: '',
  document_type: 'registration',
  document_number: '',
  issue_date: '',
  expiry_date: '',
  status: 'active',
  notes: '',
  file: null,
})

const targetOptions = computed<ReferenceOption[]>(() => {
  const lookup: Record<DocumentableType, ReferenceOption[]> = {
    vehicle: supportData.value.vehicles,
    driver: supportData.value.drivers,
    service_provider: supportData.value.service_providers,
  }

  return lookup[form.value.documentable_type]
})

watch(() => form.value.documentable_type, () => {
  if (!targetOptions.value.some((target) => target.id === form.value.documentable_id)) {
    form.value.documentable_id = targetOptions.value[0]?.id ?? null
  }
})

async function loadSupportData() {
  supportData.value = await getResource<AssetDocumentSupportData>('/asset-documents/support-data')

  if (!isEditMode.value && !form.value.documentable_id && targetOptions.value[0]) {
    form.value.documentable_id = targetOptions.value[0].id
  }
}

async function loadDocument() {
  if (!isEditMode.value) {
    return
  }

  const document = await getResource<AssetDocument>(`/asset-documents/${route.params.id}`)
  existingDocument.value = document
  form.value = {
    documentable_type: document.documentable_type,
    documentable_id: document.documentable_id,
    name: document.name,
    document_type: document.document_type,
    document_number: document.document_number ?? '',
    issue_date: document.issue_date ?? '',
    expiry_date: document.expiry_date ?? '',
    status: document.status,
    notes: document.notes ?? '',
    file: null,
  }
}

function handleFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  form.value.file = input.files?.[0] ?? null
}

function buildPayload(): FormData {
  const payload = new FormData()

  payload.append('documentable_type', form.value.documentable_type)

  if (form.value.documentable_id !== null) {
    payload.append('documentable_id', String(form.value.documentable_id))
  }

  payload.append('name', form.value.name)
  payload.append('document_type', form.value.document_type)
  payload.append('status', form.value.status)

  if (form.value.document_number) payload.append('document_number', form.value.document_number)
  if (form.value.issue_date) payload.append('issue_date', form.value.issue_date)
  if (form.value.expiry_date) payload.append('expiry_date', form.value.expiry_date)
  if (form.value.notes) payload.append('notes', form.value.notes)
  if (form.value.file) payload.append('file', form.value.file)

  return payload
}

async function submit() {
  submitting.value = true
  errorMessage.value = null
  fieldErrors.value = {}

  try {
    const payload = buildPayload()

    if (isEditMode.value) {
      payload.append('_method', 'PUT')
      await api.post<ApiResponse<AssetDocument>>(`/asset-documents/${route.params.id}`, payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    } else {
      await api.post<ApiResponse<AssetDocument>>('/asset-documents', payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    }

    await router.push({ name: 'asset-documents' })
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
    await Promise.all([loadSupportData(), loadDocument()])
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
      :title="isEditMode ? 'Edit asset document' : 'Add asset document'"
      description="Keep document evidence tenant-safe and target-aware so later renewal reminders and audits have clean source records."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'asset-documents' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
        >
          Cancel
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to save document"
      :description="errorMessage"
      tone="danger"
    />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.95fr]" @submit.prevent="submit">
      <SectionCard title="Document details" description="Choose the target record first, then capture the document metadata and lifecycle dates.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Target type</span>
            <select v-model="form.documentable_type" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option v-for="option in documentableTypeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('documentable_type')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Target record</span>
            <select v-model="form.documentable_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option :value="null">Select record</option>
              <option v-for="target in targetOptions" :key="target.id" :value="target.id">
                {{ target.label }}{{ target.secondary ? ` · ${target.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('documentable_id')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200 md:col-span-2">
            <span class="font-medium">Document name</span>
            <input v-model="form.name" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('name')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Document type</span>
            <select v-model="form.document_type" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option v-for="option in assetDocumentTypeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('document_type')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Document number</span>
            <input v-model="form.document_number" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('document_number')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Issue date</span>
            <input v-model="form.issue_date" type="date" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('issue_date')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Expiry date</span>
            <input v-model="form.expiry_date" type="date" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
            <FieldError :errors="errorsFor('expiry_date')" />
          </label>
        </div>
      </SectionCard>

      <SectionCard title="File and lifecycle" description="Uploads stay behind the storage abstraction so local disk or S3-compatible backends can be swapped later without changing the UI contract.">
        <div class="space-y-4 text-sm text-slate-700 dark:text-slate-200">
          <label class="space-y-2">
            <span class="font-medium">Status</span>
            <select v-model="form.status" class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading">
              <option v-for="option in assetDocumentStatusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('status')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Document file</span>
            <input
              type="file"
              accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
              class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm outline-none file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-semibold file:text-slate-700"
              :disabled="loading"
              @change="handleFileChange"
            >
            <FieldError :errors="errorsFor('file')" />
          </label>
          <div v-if="existingDocument?.file_name" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-xs leading-5 text-slate-600 dark:text-slate-400">
            Current file: <span class="font-semibold text-slate-800 dark:text-slate-100">{{ existingDocument.file_name }}</span>
            <a v-if="existingDocument.download_url" :href="existingDocument.download_url" class="ml-2 font-semibold text-blue-700 dark:text-blue-200 hover:text-blue-900 dark:hover:text-blue-100">Download</a>
          </div>
          <label class="space-y-2">
            <span class="font-medium">Notes</span>
            <textarea v-model="form.notes" class="min-h-28 w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500" :disabled="loading" />
            <FieldError :errors="errorsFor('notes')" />
          </label>
          <p class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-xs leading-5 text-slate-600 dark:text-slate-400">
            Supported uploads: PDF, Office files, and common image formats up to 10 MB. File replacement updates the current record while keeping the document audit trail in the backend.
          </p>
          <div class="flex flex-col gap-3 sm:flex-row">
            <RouterLink :to="{ name: 'asset-documents' }" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50 sm:w-auto">
              Cancel
            </RouterLink>
            <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="loading || submitting">
              {{ submitting ? 'Saving...' : isEditMode ? 'Save document' : 'Create document' }}
            </button>
          </div>
        </div>
      </SectionCard>
    </form>
  </div>
</template>
