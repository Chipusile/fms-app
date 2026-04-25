<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { inspectionTemplateStatusOptions } from '@/lib/fleet-options'
import { destroyResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type { ApiError, InspectionTemplate, PaginationMeta } from '@/types'

const auth = useAuthStore()
const templates = ref<InspectionTemplate[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const canCreate = computed(() => auth.hasPermission('inspection-templates.create'))
const canManage = computed(() => auth.hasAnyPermission(['inspection-templates.update', 'inspection-templates.delete']))
const columns = computed(() => (
  canManage.value
    ? [
        { key: 'name', label: 'Template' },
        { key: 'code', label: 'Code' },
        { key: 'applies_to', label: 'Applies to' },
        { key: 'items_count', label: 'Checklist items' },
        { key: 'status', label: 'Status' },
        { key: 'actions', label: 'Actions' },
      ]
    : [
        { key: 'name', label: 'Template' },
        { key: 'code', label: 'Code' },
        { key: 'applies_to', label: 'Applies to' },
        { key: 'items_count', label: 'Checklist items' },
        { key: 'status', label: 'Status' },
      ]
))

const rows = computed(() => templates.value.map((template) => ({
  id: template.id,
  name: template.name,
  code: template.code,
  applies_to: template.applies_to,
  items_count: template.items?.length ?? 0,
  status: template.status,
})))

async function loadTemplates(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<InspectionTemplate>('/inspection-templates', {
      page,
      search: search.value || undefined,
      filter: {
        status: statusFilter.value,
      },
    })

    templates.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function removeTemplate(id: number) {
  const template = templates.value.find((item) => item.id === id)

  if (!template || !globalThis.confirm(`Delete inspection template ${template.name}?`)) {
    return
  }

  try {
    await destroyResource(`/inspection-templates/${id}`)
    await loadTemplates(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

onMounted(async () => {
  await loadTemplates()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Operations"
      title="Inspection Templates"
      description="Checklist templates define reusable inspection standards without hardcoding vehicle readiness rules into the product."
    >
      <template #actions>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'inspection-templates.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Add template
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load inspection templates"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadTemplates(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search template name or code"
          class="min-w-[240px] flex-1 rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option
            v-for="option in inspectionTemplateStatusOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
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
      empty-title="No inspection templates defined"
      empty-description="Create the first checklist template before recording inspections."
    >
      <template #cell-applies_to="{ value }">
        {{ String(value).replaceAll('_', ' ') }}
      </template>
      <template #cell-status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template
        v-if="canManage"
        #cell-actions="{ row }"
      >
        <div class="flex items-center gap-2">
          <RouterLink
            v-if="auth.hasPermission('inspection-templates.update')"
            :to="{ name: 'inspection-templates.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
          >
            Edit
          </RouterLink>
          <button
            v-if="auth.hasPermission('inspection-templates.delete')"
            type="button"
            class="rounded-xl border border-rose-300 dark:border-rose-800/60 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:text-rose-200 transition hover:bg-rose-50 dark:hover:bg-rose-950/40"
            @click="removeTemplate(Number(row.id))"
          >
            Delete
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadTemplates(meta.current_page - 1)"
      @next="loadTemplates(meta.current_page + 1)"
    />
  </div>
</template>
