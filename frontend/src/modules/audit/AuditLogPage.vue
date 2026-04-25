<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import { listResource } from '@/lib/resource-client'
import type { ApiError, AuditLog, PaginationMeta } from '@/types'

const columns = [
  { key: 'event', label: 'Event' },
  { key: 'entity', label: 'Entity' },
  { key: 'actor', label: 'Actor' },
  { key: 'when', label: 'Timestamp' },
]

const logs = ref<AuditLog[]>([])
const loading = ref(false)
const errorMessage = ref<string | null>(null)
const eventFilter = ref('')
const meta = ref<PaginationMeta>({
  current_page: 1,
  last_page: 1,
  per_page: 25,
  total: 0,
})

const rows = computed(() => logs.value.map((log) => ({
  event: log.event,
  entity: `${log.auditable_type.split('\\').pop()} #${log.auditable_id}`,
  actor: log.user?.name ?? 'System',
  when: new Date(log.created_at).toLocaleString(),
})))

async function loadLogs(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<AuditLog>('/audit-logs', {
      page,
      per_page: 25,
      filter: {
        event: eventFilter.value,
      },
    })

    logs.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadLogs()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Assurance"
      title="Audit logs"
      description="Audit records are immutable and tenant-aware. Sensitive changes should be traceable by actor, event, and entity."
    />

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load audit logs"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadLogs(1)">
      <FilterBar>
        <select
          v-model="eventFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All events</option>
          <option value="created">created</option>
          <option value="updated">updated</option>
          <option value="deleted">deleted</option>
          <option value="force_deleted">force_deleted</option>
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
      empty-title="No audit records"
      empty-description="State-changing business operations will appear here as modules come online."
    />

    <PaginationBar
      :meta="meta"
      @previous="loadLogs(meta.current_page - 1)"
      @next="loadLogs(meta.current_page + 1)"
    />
  </div>
</template>
