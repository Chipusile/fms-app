<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { complianceCategoryOptions, complianceStatusOptions, compliantTypeOptions } from '@/lib/fleet-options'
import { destroyResource, getResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  ComplianceDashboard,
  ComplianceItem,
  PaginationMeta,
} from '@/types'

const auth = useAuthStore()
const complianceItems = ref<ComplianceItem[]>([])
const dashboard = ref<ComplianceDashboard>({
  totals: { all: 0, valid: 0, expiring_soon: 0, expired: 0, waived: 0 },
  by_category: {},
  entity_mix: { vehicles: 0, drivers: 0 },
  expiring_items: [],
})
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const search = ref('')
const statusFilter = ref('')
const categoryFilter = ref('')
const compliantTypeFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const canCreate = computed(() => auth.hasPermission('compliance.create'))
const canManage = computed(() => auth.hasAnyPermission(['compliance.update', 'compliance.delete']))

const columns = computed(() => (
  canManage.value
    ? [
        { key: 'title', label: 'Requirement' },
        { key: 'category', label: 'Category' },
        { key: 'compliant', label: 'Entity' },
        { key: 'expiry_date', label: 'Expiry' },
        { key: 'status', label: 'Status' },
        { key: 'actions', label: 'Actions' },
      ]
    : [
        { key: 'title', label: 'Requirement' },
        { key: 'category', label: 'Category' },
        { key: 'compliant', label: 'Entity' },
        { key: 'expiry_date', label: 'Expiry' },
        { key: 'status', label: 'Status' },
      ]
))

const rows = computed(() => complianceItems.value.map((item) => ({
  id: item.id,
  title: item.title,
  category: item.category.replaceAll('_', ' '),
  compliant: item.compliant?.label ?? 'Unknown entity',
  expiry_date: formatExpiry(item.expiry_date, item.days_until_expiry),
  status: item.status,
})))

function formatExpiry(value: string | null, daysUntilExpiry: number | null): string {
  if (!value) return 'No expiry'

  const date = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))

  if (daysUntilExpiry === null) {
    return date
  }

  const suffix = daysUntilExpiry >= 0 ? `${daysUntilExpiry} days left` : `${Math.abs(daysUntilExpiry)} days overdue`
  return `${date} · ${suffix}`
}

async function loadDashboard() {
  try {
    dashboard.value = await getResource<ComplianceDashboard>('/compliance-items/dashboard')
  } catch {
    dashboard.value = {
      totals: { all: 0, valid: 0, expiring_soon: 0, expired: 0, waived: 0 },
      by_category: {},
      entity_mix: { vehicles: 0, drivers: 0 },
      expiring_items: [],
    }
  }
}

async function loadComplianceItems(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<ComplianceItem>('/compliance-items', {
      page,
      search: search.value || undefined,
      filter: {
        status: statusFilter.value,
        category: categoryFilter.value,
        compliant_type: compliantTypeFilter.value,
      },
    })

    complianceItems.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function removeComplianceItem(id: number) {
  const target = complianceItems.value.find((item) => item.id === id)

  if (!target || !globalThis.confirm(`Delete compliance item ${target.title}?`)) {
    return
  }

  try {
    await destroyResource(`/compliance-items/${id}`)
    await Promise.all([loadComplianceItems(meta.value.current_page), loadDashboard()])
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

onMounted(async () => {
  await Promise.all([loadDashboard(), loadComplianceItems()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Compliance"
      title="Compliance Register"
      description="Monitor insurance, licensing, permits, and regulatory obligations across vehicles and drivers with tenant-scoped visibility."
    >
      <template #actions>
        <RouterLink
          v-if="canCreate"
          :to="{ name: 'compliance.create' }"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
          Create compliance item
        </RouterLink>
      </template>
    </PageHeader>

    <div class="grid gap-4 lg:grid-cols-4">
      <div class="rounded-2xl border border-slate-200 bg-white/90 p-5 shadow-sm shadow-slate-200/60">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">All items</p>
        <p class="mt-3 text-3xl font-semibold text-slate-950">{{ dashboard.totals.all }}</p>
        <p class="mt-2 text-sm text-slate-600">Tracked compliance records for the active tenant.</p>
      </div>
      <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 shadow-sm shadow-emerald-100/60">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Valid</p>
        <p class="mt-3 text-3xl font-semibold text-emerald-950">{{ dashboard.totals.valid }}</p>
        <p class="mt-2 text-sm text-emerald-900/80">Records safely outside the configured reminder window.</p>
      </div>
      <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm shadow-amber-100/60">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Expiring soon</p>
        <p class="mt-3 text-3xl font-semibold text-amber-950">{{ dashboard.totals.expiring_soon }}</p>
        <p class="mt-2 text-sm text-amber-900/80">Items nearing expiry based on tenant reminder thresholds.</p>
      </div>
      <div class="rounded-2xl border border-rose-200 bg-rose-50/70 p-5 shadow-sm shadow-rose-100/60">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">Expired</p>
        <p class="mt-3 text-3xl font-semibold text-rose-950">{{ dashboard.totals.expired }}</p>
        <p class="mt-2 text-sm text-rose-900/80">Records already outside their valid operating window.</p>
      </div>
    </div>

    <InlineAlert
      v-if="dashboard.expiring_items.length > 0"
      title="Priority renewals"
      :description="`Next due: ${dashboard.expiring_items.map((item) => item.title).slice(0, 3).join(', ')}${dashboard.expiring_items.length > 3 ? '…' : ''}`"
      tone="info"
    />

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load compliance records"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadComplianceItems(1)">
      <FilterBar>
        <input
          v-model="search"
          type="search"
          placeholder="Search title, reference number, or issuer"
          class="min-w-[240px] flex-1 rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option v-for="option in complianceStatusOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="categoryFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All categories</option>
          <option v-for="option in complianceCategoryOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="compliantTypeFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All entity types</option>
          <option v-for="option in compliantTypeOptions" :key="option.value" :value="option.value">
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
      empty-title="No compliance items tracked"
      empty-description="Add the first compliance record to start renewal tracking for drivers or vehicles."
    >
      <template #cell-status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template v-if="canManage" #cell-actions="{ row }">
        <div class="flex items-center gap-2">
          <RouterLink
            :to="{ name: 'compliance.edit', params: { id: String(row.id) } }"
            class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
          >
            Open
          </RouterLink>
          <button
            v-if="auth.hasPermission('compliance.delete')"
            type="button"
            class="rounded-xl border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
            @click="removeComplianceItem(Number(row.id))"
          >
            Delete
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadComplianceItems(meta.current_page - 1)"
      @next="loadComplianceItems(meta.current_page + 1)"
    />
  </div>
</template>
