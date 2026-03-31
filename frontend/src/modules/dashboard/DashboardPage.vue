<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import MetricCard from '@/components/ui/MetricCard.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { getResource, queryResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type { ApiError, DashboardAnalytics, DashboardChart, ReportSupportData } from '@/types'

const AnalyticsChart = defineAsyncComponent(() => import('@/components/charts/AnalyticsChart.vue'))

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

function formatDate(value: string | null): string {
  if (!value) return 'No due date'

  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
}

function formatDistance(value: number): string {
  return new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 2,
  }).format(value)
}

const auth = useAuthStore()
const supportData = ref<ReportSupportData | null>(null)
const analytics = ref<DashboardAnalytics | null>(null)
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const dateFrom = ref(defaultDateFrom())
const dateTo = ref(defaultDateTo())
const vehicleFilter = ref('')
const departmentFilter = ref('')

const chartSections = computed<Array<{ key: string; title: string; description: string; chart: DashboardChart | null }>>(() => [
  {
    key: 'fleet_status',
    title: 'Fleet status mix',
    description: 'Current asset status distribution for the selected reporting scope.',
    chart: analytics.value?.charts.fleet_status ?? null,
  },
  {
    key: 'trip_status',
    title: 'Trip workflow',
    description: 'Trip progression counts across the selected period.',
    chart: analytics.value?.charts.trip_status ?? null,
  },
  {
    key: 'cost_trend',
    title: 'Cost trend',
    description: 'Fuel versus maintenance spend across the current monthly window.',
    chart: analytics.value?.charts.cost_trend ?? null,
  },
  {
    key: 'utilization_top',
    title: 'Utilization leaders',
    description: 'Top vehicles by completed distance within the selected period.',
    chart: analytics.value?.charts.utilization_top ?? null,
  },
  {
    key: 'compliance_status',
    title: 'Compliance posture',
    description: 'Renewal status split for filtered compliance records.',
    chart: analytics.value?.charts.compliance_status ?? null,
  },
  {
    key: 'incident_trend',
    title: 'Incident trend',
    description: 'Incident count versus critical severity over the current monthly buckets.',
    chart: analytics.value?.charts.incident_trend ?? null,
  },
])

const canViewReports = computed(() => auth.hasPermission('reports.view'))

async function loadSupportData() {
  try {
    supportData.value = await getResource<ReportSupportData>('/reports/support-data')
  } catch {
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

async function loadDashboard() {
  if (!canViewReports.value) {
    analytics.value = null
    return
  }

  loading.value = true
  errorMessage.value = null

  try {
    const response = await queryResource<DashboardAnalytics>('/reports/dashboard', {
      filter: {
        date_from: dateFrom.value,
        date_to: dateTo.value,
        vehicle_id: vehicleFilter.value,
        department_id: departmentFilter.value,
      },
    })

    analytics.value = response.data
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (!canViewReports.value) {
    return
  }

  await Promise.all([loadSupportData(), loadDashboard()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Analytics"
      title="Fleet analytics overview"
      description="Monitor operational throughput, spend, compliance risk, and maintenance exposure through tenant-aware dashboard tiles and trend charts."
    >
      <template #actions>
        <RouterLink
          v-if="canViewReports"
          :to="{ name: 'reports' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
        >
          Open report center
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="!canViewReports"
      title="Reporting permission required"
      description="This analytics dashboard is available to users with reports access. Ask a tenant administrator to grant the relevant permission if you need KPI and export visibility."
      tone="warning"
    />

    <template v-else>
      <InlineAlert
        v-if="errorMessage"
        title="Unable to load dashboard analytics"
        :description="errorMessage"
        tone="danger"
      />

      <form @submit.prevent="loadDashboard">
        <FilterBar>
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
          <button
            type="submit"
            class="rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
          >
            Apply filters
          </button>
        </FilterBar>
      </form>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <MetricCard
          v-for="metric in analytics?.metrics ?? []"
          :key="metric.label"
          :label="metric.label"
          :value="metric.value"
          :hint="metric.hint ?? undefined"
          :tone="metric.tone"
        />
      </div>

      <div class="grid gap-6 xl:grid-cols-2">
        <SectionCard
          v-for="section in chartSections"
          :key="section.key"
          :title="section.title"
          :description="section.description"
        >
          <AnalyticsChart
            :chart="section.chart ?? { type: 'bar', categories: [], series: [] }"
            height="340px"
          />
        </SectionCard>
      </div>

      <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <SectionCard
          title="Top utilization vehicles"
          description="Vehicles with the highest completed distance inside the current dashboard scope."
        >
          <div
            v-if="(analytics?.highlights.top_utilization_vehicles.length ?? 0) > 0"
            class="space-y-3"
          >
            <div
              v-for="vehicle in analytics?.highlights.top_utilization_vehicles ?? []"
              :key="vehicle.label"
              class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
            >
              <div>
                <p class="font-semibold text-slate-900">{{ vehicle.label }}</p>
                <p class="text-sm text-slate-600">{{ vehicle.trip_count }} trips</p>
              </div>
              <p class="text-sm font-semibold text-slate-900">
                {{ formatDistance(vehicle.distance_km) }} km
              </p>
            </div>
          </div>
          <p
            v-else
            class="text-sm leading-6 text-slate-500"
          >
            No utilization data is available for the selected filter combination.
          </p>
        </SectionCard>

        <SectionCard
          title="Urgent compliance"
          description="Compliance items nearing expiry or already outside the valid operating window."
        >
          <div
            v-if="(analytics?.highlights.urgent_compliance_items.length ?? 0) > 0"
            class="space-y-3"
          >
            <div
              v-for="item in analytics?.highlights.urgent_compliance_items ?? []"
              :key="`${item.title}-${item.entity}`"
              class="rounded-2xl border border-slate-200 bg-white px-4 py-3"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="font-semibold text-slate-900">{{ item.title }}</p>
                  <p class="text-sm text-slate-600">{{ item.entity }}</p>
                  <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">
                    {{ formatDate(item.expiry_date) }}
                  </p>
                </div>
                <StatusBadge :value="item.status" />
              </div>
            </div>
          </div>
          <p
            v-else
            class="text-sm leading-6 text-slate-500"
          >
            No urgent compliance renewals were found for the current dashboard scope.
          </p>
        </SectionCard>
      </div>

      <div class="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
        <SectionCard
          title="Maintenance health"
          description="Active due-soon versus overdue maintenance obligations across schedules and components."
        >
          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Due soon schedules</p>
              <p class="mt-2 text-2xl font-semibold text-amber-950">
                {{ analytics?.highlights.maintenance_health.due_soon_schedules ?? 0 }}
              </p>
            </div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50/70 p-4">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">Overdue schedules</p>
              <p class="mt-2 text-2xl font-semibold text-rose-950">
                {{ analytics?.highlights.maintenance_health.overdue_schedules ?? 0 }}
              </p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Due soon components</p>
              <p class="mt-2 text-2xl font-semibold text-amber-950">
                {{ analytics?.highlights.maintenance_health.due_soon_components ?? 0 }}
              </p>
            </div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50/70 p-4">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-700">Overdue components</p>
              <p class="mt-2 text-2xl font-semibold text-rose-950">
                {{ analytics?.highlights.maintenance_health.overdue_components ?? 0 }}
              </p>
            </div>
          </div>
        </SectionCard>

        <SectionCard
          title="Urgent maintenance"
          description="Most urgent schedule and component items requiring planning or intervention."
        >
          <div
            v-if="(analytics?.highlights.urgent_maintenance_items.length ?? 0) > 0"
            class="space-y-3"
          >
            <div
              v-for="item in analytics?.highlights.urgent_maintenance_items ?? []"
              :key="`${item.source}-${item.asset}-${item.title}`"
              class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="font-semibold text-slate-900">{{ item.title }}</p>
                  <p class="text-sm text-slate-600">{{ item.asset }}</p>
                  <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">{{ item.source }}</p>
                </div>
                <StatusBadge :value="item.status" />
              </div>
            </div>
          </div>
          <p
            v-else
            class="text-sm leading-6 text-slate-500"
          >
            No urgent maintenance items were found for the current filter set.
          </p>
        </SectionCard>
      </div>

      <div
        v-if="loading"
        class="rounded-2xl border border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500"
      >
        Loading dashboard analytics...
      </div>
    </template>
  </div>
</template>
