<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import MetricCard from '@/components/ui/MetricCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import {
  complianceCategoryOptions,
  complianceStatusOptions,
  incidentSeverityOptions,
  incidentStatusOptions,
  tripStatusOptions,
  vehicleStatusOptions,
} from '@/lib/fleet-options'
import { createResource, getResource, listResource, queryResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  PaginationMeta,
  ReportDataset,
  ReportExport,
  ReportExportPayload,
  ReportSupportData,
  ReportType,
} from '@/types'

function isoDate(value: Date): string {
  return value.toISOString().slice(0, 10)
}

function defaultDateFrom(): string {
  const date = new Date()
  date.setDate(date.getDate() - 29)
  return isoDate(date)
}

function defaultDateTo(): string {
  return isoDate(new Date())
}

function humanize(value: string): string {
  return value.replaceAll('_', ' ')
}

function formatDate(value: string): string {
  const parsed = new Date(value)

  if (Number.isNaN(parsed.getTime())) {
    return value
  }

  const hasTime = value.includes('T') || value.includes(':')

  return new Intl.DateTimeFormat(undefined, hasTime ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' }).format(parsed)
}

function formatNumber(value: number): string {
  return new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 2,
  }).format(value)
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
  }).format(value)
}

const auth = useAuthStore()
const supportData = ref<ReportSupportData | null>(null)
const report = ref<ReportDataset | null>(null)
const exports = ref<ReportExport[]>([])
const loading = ref(false)
const exportsLoading = ref(false)
const errorMessage = ref<string | null>(null)
const exportMessage = ref<string | null>(null)
const reportMeta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const exportMeta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 6, total: 0 })

const reportType = ref<ReportType>('fleet-overview')
const search = ref('')
const dateFrom = ref(defaultDateFrom())
const dateTo = ref(defaultDateTo())
const vehicleFilter = ref('')
const departmentFilter = ref('')
const statusFilter = ref('')
const categoryFilter = ref('')
const severityFilter = ref('')

const endpointMap: Record<ReportType, string> = {
  'fleet-overview': '/reports/fleet-overview',
  'vehicle-utilization': '/reports/vehicle-utilization',
  'fuel-consumption': '/reports/fuel-consumption',
  'maintenance-cost': '/reports/maintenance-cost',
  'compliance-status': '/reports/compliance-status',
  'incident-summary': '/reports/incident-summary',
}

const canExport = computed(() => auth.hasPermission('reports.export'))
const reportTypeDefinition = computed(() => (
  supportData.value?.report_types.find((item) => item.key === reportType.value) ?? null
))
const statusOptions = computed(() => {
  switch (reportType.value) {
    case 'fleet-overview':
      return vehicleStatusOptions
    case 'vehicle-utilization':
      return tripStatusOptions
    case 'compliance-status':
      return complianceStatusOptions
    case 'incident-summary':
      return incidentStatusOptions
    default:
      return []
  }
})
const showStatusFilter = computed(() => statusOptions.value.length > 0)
const showCategoryFilter = computed(() => reportType.value === 'compliance-status')
const showSeverityFilter = computed(() => reportType.value === 'incident-summary')
const exportDownloadBase = computed(() => import.meta.env.VITE_API_BASE_URL?.replace(/\/api\/v1$/, '') ?? '')

const exportRows = computed(() => exports.value.map((item) => ({
  id: item.id,
  report_type: supportData.value?.report_types.find((reportTypeOption) => reportTypeOption.key === item.report_type)?.label ?? humanize(item.report_type),
  status: item.status,
  row_count: item.row_count ?? '—',
  requested_by: item.requester?.name ?? 'System',
  created_at: formatDate(item.created_at),
  download_url: item.download_url,
})))

const reportRows = computed(() => (
  report.value?.rows.map((row) => {
    const formattedEntries = Object.entries(row).map(([key, value]) => {
      if (value === null || value === undefined || value === '') {
        return [key, '—']
      }

      if (key === 'status' || key === 'severity') {
        return [key, value]
      }

      if (typeof value === 'string' && (key.endsWith('_date') || key.endsWith('_at'))) {
        return [key, value === '—' ? value : formatDate(value)]
      }

      if (typeof value === 'string' && ['category', 'incident_type'].includes(key)) {
        return [key, humanize(value)]
      }

      if (typeof value === 'number' && key.includes('cost')) {
        return [key, formatCurrency(value)]
      }

      if (typeof value === 'number' && (key.includes('km') || key.includes('liters') || key.includes('hours'))) {
        return [key, formatNumber(value)]
      }

      return [key, value]
    })

    return Object.fromEntries(formattedEntries)
  }) ?? []
))

function filterPayload() {
  return {
    date_from: dateFrom.value,
    date_to: dateTo.value,
    vehicle_id: vehicleFilter.value,
    department_id: departmentFilter.value,
    status: statusFilter.value,
    category: categoryFilter.value,
    severity: severityFilter.value,
  }
}

async function loadSupportData() {
  try {
    supportData.value = await getResource<ReportSupportData>('/reports/support-data')
  } catch (error) {
    errorMessage.value = (error as ApiError).message
    supportData.value = {
      report_types: [],
      vehicles: [],
      departments: [],
      trip_statuses: [],
      work_order_statuses: [],
      compliance_categories: [],
      compliance_statuses: [],
      incident_statuses: [],
      incident_severities: [],
      export_formats: [],
    }
  }
}

async function loadReport(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await queryResource<ReportDataset>(endpointMap[reportType.value], {
      page,
      search: search.value || undefined,
      filter: filterPayload(),
    })

    report.value = response.data
    reportMeta.value = response.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: 0 }
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function loadExports(page = 1) {
  exportsLoading.value = true

  try {
    const response = await listResource<ReportExport>('/reports/exports', {
      page,
      per_page: 6,
    })

    exports.value = response.data
    exportMeta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    exportsLoading.value = false
  }
}

async function queueExport() {
  if (!canExport.value) {
    return
  }

  exportMessage.value = null

  try {
    const payload: ReportExportPayload = {
      type: reportType.value,
      format: 'csv',
      search: search.value || undefined,
      filter: {
        date_from: dateFrom.value,
        date_to: dateTo.value,
        vehicle_id: vehicleFilter.value ? Number(vehicleFilter.value) : null,
        department_id: departmentFilter.value ? Number(departmentFilter.value) : null,
        status: statusFilter.value || null,
        category: categoryFilter.value || null,
        severity: severityFilter.value || null,
      },
    }

    const created = await createResource<ReportExport, ReportExportPayload>('/reports/exports', payload)
    exportMessage.value = created.status === 'completed'
      ? 'Export generated successfully and is ready for download.'
      : 'Export queued successfully. Refresh the exports list if processing takes longer than expected.'
    await loadExports(1)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

watch(reportType, () => {
  statusFilter.value = ''
  categoryFilter.value = ''
  severityFilter.value = ''
  loadReport(1)
})

onMounted(async () => {
  await Promise.all([loadSupportData(), loadReport(), loadExports()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Reporting"
      title="Report center"
      :description="reportTypeDefinition?.description ?? 'Explore tenant-scoped operational and cost datasets with reusable filters, export history, and pagination-ready tables.'"
    >
      <template #actions>
        <button
          v-if="canExport"
          type="button"
          class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
          @click="queueExport"
        >
          Export CSV
        </button>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load reporting data"
      :description="errorMessage"
      tone="danger"
    />

    <InlineAlert
      v-else-if="exportMessage"
      title="Export status"
      :description="exportMessage"
      tone="success"
    />

    <form @submit.prevent="loadReport(1)">
      <FilterBar>
        <select
          v-model="reportType"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option
            v-for="option in supportData?.report_types ?? []"
            :key="option.key"
            :value="option.key"
          >
            {{ option.label }}
          </option>
        </select>
        <input
          v-model="search"
          type="search"
          placeholder="Search current report"
          class="min-w-[220px] flex-1 rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <input
          v-model="dateFrom"
          type="date"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <input
          v-model="dateTo"
          type="date"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
        <select
          v-model="vehicleFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All vehicles</option>
          <option
            v-for="vehicle in supportData?.vehicles ?? []"
            :key="vehicle.id"
            :value="String(vehicle.id)"
          >
            {{ vehicle.label }}
          </option>
        </select>
        <select
          v-model="departmentFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All departments</option>
          <option
            v-for="department in supportData?.departments ?? []"
            :key="department.id"
            :value="String(department.id)"
          >
            {{ department.label }}
          </option>
        </select>
        <select
          v-if="showStatusFilter"
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option
            v-for="option in statusOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
        <select
          v-if="showCategoryFilter"
          v-model="categoryFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All categories</option>
          <option
            v-for="option in complianceCategoryOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
        <select
          v-if="showSeverityFilter"
          v-model="severityFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All severities</option>
          <option
            v-for="option in incidentSeverityOptions"
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

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <MetricCard
        v-for="metric in report?.summary_metrics ?? []"
        :key="metric.label"
        :label="metric.label"
        :value="metric.value"
        :hint="metric.hint ?? undefined"
        :tone="metric.tone"
      />
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
      <div class="space-y-4">
        <SectionCard
          :title="report?.title ?? 'Report dataset'"
          :description="report?.description ?? 'Select a report type to inspect the underlying operational dataset.'"
        >
          <DataTable
            :columns="report?.columns ?? []"
            :rows="reportRows"
            :loading="loading"
            empty-title="No report data found"
            empty-description="Adjust the current filters or widen the date range to populate this report."
          >
            <template #cell-status="{ value }">
              <StatusBadge :value="String(value)" />
            </template>
            <template #cell-severity="{ value }">
              <StatusBadge :value="String(value)" />
            </template>
          </DataTable>
        </SectionCard>

        <PaginationBar
          :meta="reportMeta"
          @previous="loadReport(reportMeta.current_page - 1)"
          @next="loadReport(reportMeta.current_page + 1)"
        />
      </div>

      <div class="space-y-4">
        <SectionCard
          title="Recent exports"
          description="Tenant-scoped export jobs for the report center. Completed jobs remain downloadable from this queue."
        >
          <DataTable
            :columns="[
              { key: 'report_type', label: 'Report' },
              { key: 'status', label: 'Status' },
              { key: 'row_count', label: 'Rows' },
              { key: 'actions', label: 'Actions' },
            ]"
            :rows="exportRows"
            :loading="exportsLoading"
            empty-title="No exports yet"
            empty-description="Queue the first CSV export to build an audit-ready export history."
          >
            <template #cell-status="{ value }">
              <StatusBadge :value="String(value)" />
            </template>
            <template #cell-actions="{ row }">
              <a
                v-if="row.download_url"
                :href="`${exportDownloadBase}${String(row.download_url)}`"
                class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
              >
                Download
              </a>
              <span
                v-else
                class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500"
              >
                Pending
              </span>
            </template>
          </DataTable>
        </SectionCard>

        <PaginationBar
          :meta="exportMeta"
          @previous="loadExports(exportMeta.current_page - 1)"
          @next="loadExports(exportMeta.current_page + 1)"
        />

        <SectionCard
          title="Current report scope"
          description="Quick visibility into the filter frame currently applied to the dataset."
        >
          <dl class="space-y-3 text-sm text-slate-700">
            <div class="flex items-start justify-between gap-3">
              <dt class="font-medium text-slate-500">Window</dt>
              <dd class="text-right">{{ dateFrom }} to {{ dateTo }}</dd>
            </div>
            <div class="flex items-start justify-between gap-3">
              <dt class="font-medium text-slate-500">Vehicle</dt>
              <dd class="text-right">{{ supportData?.vehicles.find((vehicle) => String(vehicle.id) === vehicleFilter)?.label ?? 'All vehicles' }}</dd>
            </div>
            <div class="flex items-start justify-between gap-3">
              <dt class="font-medium text-slate-500">Department</dt>
              <dd class="text-right">{{ supportData?.departments.find((department) => String(department.id) === departmentFilter)?.label ?? 'All departments' }}</dd>
            </div>
            <div class="flex items-start justify-between gap-3">
              <dt class="font-medium text-slate-500">Status</dt>
              <dd class="text-right">{{ statusFilter ? humanize(statusFilter) : 'All statuses' }}</dd>
            </div>
          </dl>
        </SectionCard>
      </div>
    </div>
  </div>
</template>
