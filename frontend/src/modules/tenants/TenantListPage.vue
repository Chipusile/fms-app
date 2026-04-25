<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { tenantStatusOptions } from '@/lib/options'
import { destroyResource, listResource } from '@/lib/resource-client'
import type { ApiError, PaginationMeta, Tenant } from '@/types'

const columns = [
  { key: 'name', label: 'Tenant' },
  { key: 'slug', label: 'Slug' },
  { key: 'status', label: 'Status' },
  { key: 'region', label: 'Timezone / currency' },
  { key: 'actions', label: 'Actions' },
]

const tenants = ref<Tenant[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const meta = ref<PaginationMeta>({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
})

const rows = computed(() => tenants.value.map((tenant) => ({
  id: tenant.id,
  name: tenant.name,
  slug: tenant.slug,
  status: tenant.status,
  region: `${tenant.timezone} / ${tenant.currency}`,
})))

async function loadTenants(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<Tenant>('/tenants', {
      page,
      search: search.value || undefined,
      filter: {
        status: statusFilter.value,
      },
    })
    tenants.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadTenants()
})

async function removeTenant(id: number) {
  const target = tenants.value.find((tenant) => tenant.id === id)

  if (!target || !globalThis.confirm(`Deactivate ${target.name}?`)) {
    return
  }

  try {
    await destroyResource(`/tenants/${id}`)
    await loadTenants(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

function submitFilters() {
  void loadTenants(1)
}
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Platform"
      title="Tenants"
      description="Platform operators manage onboarding, suspension, branding, and support posture from this cross-tenant workspace."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'tenants.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Create tenant
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load tenants"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="submitFilters">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search tenants"
          class="min-w-[220px] flex-1 rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option
            v-for="option in tenantStatusOptions"
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
      empty-title="No tenants configured"
      empty-description="Create the first organisation to begin platform onboarding."
    >
      <template #cell-status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template #cell-actions="{ row }">
        <div class="flex items-center gap-2">
          <RouterLink
            :to="{ name: 'tenants.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
          >
            Edit
          </RouterLink>
          <button
            type="button"
            class="rounded-xl border border-rose-300 dark:border-rose-800/60 px-3 py-1.5 text-xs font-semibold text-rose-700 dark:text-rose-200 transition hover:bg-rose-50 dark:hover:bg-rose-950/40"
            @click="removeTenant(Number(row.id))"
          >
            Deactivate
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadTenants(meta.current_page - 1)"
      @next="loadTenants(meta.current_page + 1)"
    />
  </div>
</template>
