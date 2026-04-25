<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { serviceProviderStatusOptions, serviceProviderTypeOptions } from '@/lib/fleet-options'
import { destroyResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type { ApiError, PaginationMeta, ServiceProvider } from '@/types'

const auth = useAuthStore()
const baseColumns = [
  { key: 'name', label: 'Provider' },
  { key: 'provider_type', label: 'Type' },
  { key: 'contact_person', label: 'Contact' },
  { key: 'status', label: 'Status' },
]

const providers = ref<ServiceProvider[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const typeFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const canCreate = computed(() => auth.hasPermission('vendors.create'))
const canManage = computed(() => auth.hasAnyPermission(['vendors.update', 'vendors.delete']))
const columns = computed(() => (
  canManage.value ? [...baseColumns, { key: 'actions', label: 'Actions' }] : baseColumns
))

const rows = computed(() => providers.value.map((provider) => ({
  id: provider.id,
  name: provider.name,
  provider_type: provider.provider_type.replaceAll('_', ' '),
  contact_person: provider.contact_person ?? provider.email ?? '—',
  status: provider.status,
})))

async function loadProviders(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<ServiceProvider>('/service-providers', {
      page,
      search: search.value || undefined,
      filter: {
        status: statusFilter.value,
        provider_type: typeFilter.value,
      },
    })

    providers.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function removeProvider(id: number) {
  const target = providers.value.find((item) => item.id === id)

  if (!target || !globalThis.confirm(`Delete ${target.name}?`)) {
    return
  }

  try {
    await destroyResource(`/service-providers/${id}`)
    await loadProviders(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

onMounted(async () => {
  await loadProviders()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Partners"
      title="Service Providers"
      description="Garages, insurers, fuel stations, and support vendors are managed centrally for later maintenance and compliance workflows."
    >
      <template #actions>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'service-providers.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Add provider
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load service providers"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadProviders(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search providers"
          class="min-w-[220px] flex-1 rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="typeFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All provider types</option>
          <option
            v-for="option in serviceProviderTypeOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option
            v-for="option in serviceProviderStatusOptions"
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
      empty-title="No service providers configured"
      empty-description="Create the vendor and support provider registry before maintenance and compliance workflows begin."
    >
      <template #cell-status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template
        v-if="canManage"
        #cell-actions="{ row }"
      >
        <div class="flex items-center gap-2">
          <RouterLink
            v-if="auth.hasPermission('vendors.update')"
            :to="{ name: 'service-providers.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
          >
            Edit
          </RouterLink>
          <button
            v-if="auth.hasPermission('vendors.delete')"
            type="button"
            class="rounded-xl border border-rose-300 dark:border-rose-800/60 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:text-rose-200 transition hover:bg-rose-50 dark:hover:bg-rose-950/40"
            @click="removeProvider(Number(row.id))"
          >
            Delete
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadProviders(meta.current_page - 1)"
      @next="loadProviders(meta.current_page + 1)"
    />
  </div>
</template>
