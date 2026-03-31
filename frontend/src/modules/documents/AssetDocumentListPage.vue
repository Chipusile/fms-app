<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { assetDocumentStatusOptions, assetDocumentTypeOptions, documentableTypeOptions } from '@/lib/fleet-options'
import { destroyResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type { ApiError, AssetDocument, PaginationMeta } from '@/types'

const auth = useAuthStore()
const baseColumns = [
  { key: 'name', label: 'Document' },
  { key: 'target', label: 'Target' },
  { key: 'document_type', label: 'Type' },
  { key: 'expiry_date', label: 'Expiry' },
  { key: 'status', label: 'Status' },
  { key: 'file', label: 'File' },
]

const documents = ref<AssetDocument[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const documentableTypeFilter = ref('')
const documentTypeFilter = ref('')
const statusFilter = ref('')
const expiringWithinDaysFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const canCreate = computed(() => auth.hasPermission('documents.create'))
const canManage = computed(() => auth.hasAnyPermission(['documents.update', 'documents.delete']))
const columns = computed(() => (
  canManage.value || auth.hasPermission('documents.view')
    ? [...baseColumns, { key: 'actions', label: 'Actions' }]
    : baseColumns
))

const rows = computed(() => documents.value.map((document) => ({
  id: document.id,
  name: document.name,
  target: document.documentable
    ? `${document.documentable.label}${document.documentable.secondary ? ` · ${document.documentable.secondary}` : ''}`
    : 'Unknown target',
  document_type: document.document_type.replaceAll('_', ' '),
  expiry_date: document.expiry_date ?? 'No expiry',
  status: document.status,
  file: document.file_name ?? 'Metadata only',
  has_file: document.has_file,
  download_url: document.download_url,
})))

async function loadDocuments(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<AssetDocument>('/asset-documents', {
      page,
      search: search.value || undefined,
      filter: {
        documentable_type: documentableTypeFilter.value,
        document_type: documentTypeFilter.value,
        status: statusFilter.value,
        expiring_within_days: expiringWithinDaysFilter.value,
      },
    })

    documents.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function removeDocument(id: number) {
  const target = documents.value.find((item) => item.id === id)

  if (!target || !globalThis.confirm(`Delete ${target.name}?`)) {
    return
  }

  try {
    await destroyResource(`/asset-documents/${id}`)
    await loadDocuments(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

onMounted(async () => {
  await loadDocuments()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Compliance"
      title="Asset documents"
      description="Upload and monitor document evidence for vehicles, drivers, and service providers without hardwiring the workflow to one asset type."
    >
      <template #actions>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'asset-documents.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Add document
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load documents"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadDocuments(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search documents"
          class="min-w-[220px] flex-1 rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select v-model="documentableTypeFilter" class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500">
          <option value="">All target types</option>
          <option v-for="option in documentableTypeOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <select v-model="documentTypeFilter" class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500">
          <option value="">All document types</option>
          <option v-for="option in assetDocumentTypeOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <select v-model="statusFilter" class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500">
          <option value="">All statuses</option>
          <option v-for="option in assetDocumentStatusOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <select v-model="expiringWithinDaysFilter" class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500">
          <option value="">Any expiry window</option>
          <option value="30">Expiring in 30 days</option>
          <option value="60">Expiring in 60 days</option>
          <option value="90">Expiring in 90 days</option>
        </select>
        <button
          type="submit"
          class="rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Apply
        </button>
      </FilterBar>
    </form>

    <DataTable
      :columns="columns"
      :rows="rows"
      :loading="loading"
      empty-title="No documents recorded"
      empty-description="Attach the first compliance or onboarding document before downstream renewals and alerts are introduced."
    >
      <template #cell-status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template #cell-actions="{ row }">
        <div class="flex items-center gap-2">
          <a
            v-if="row.has_file"
            :href="String(row.download_url)"
            class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
          >
            Download
          </a>
          <RouterLink
            v-if="auth.hasPermission('documents.update')"
            :to="{ name: 'asset-documents.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
          >
            Edit
          </RouterLink>
          <button
            v-if="auth.hasPermission('documents.delete')"
            type="button"
            class="rounded-xl border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
            @click="removeDocument(Number(row.id))"
          >
            Delete
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadDocuments(meta.current_page - 1)"
      @next="loadDocuments(meta.current_page + 1)"
    />
  </div>
</template>
