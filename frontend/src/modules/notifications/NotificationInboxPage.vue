<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import api from '@/plugins/axios'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { userNotificationStatusOptions, userNotificationTypeOptions } from '@/lib/fleet-options'
import { listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  ApiResponse,
  NotificationMeta,
  UserNotification,
} from '@/types'

const auth = useAuthStore()
const notifications = ref<UserNotification[]>([])
const loading = ref(false)
const actionLoading = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const statusFilter = ref('')
const typeFilter = ref('')
const meta = ref<NotificationMeta>({ current_page: 1, last_page: 1, per_page: 20, total: 0, unread_count: 0 })

const canUpdate = computed(() => auth.hasPermission('notifications.update'))

const columns = computed(() => (
  canUpdate.value
    ? [
        { key: 'title', label: 'Notification' },
        { key: 'type', label: 'Type' },
        { key: 'created_at', label: 'Created' },
        { key: 'status', label: 'Status' },
        { key: 'actions', label: 'Actions' },
      ]
    : [
        { key: 'title', label: 'Notification' },
        { key: 'type', label: 'Type' },
        { key: 'created_at', label: 'Created' },
        { key: 'status', label: 'Status' },
      ]
))

const rows = computed(() => notifications.value.map((notification) => ({
  id: notification.id,
  title: notification.title,
  body: notification.body ?? '',
  type: notification.type,
  action_url: notification.action_url,
  status: notification.status,
  created_at: formatDateTime(notification.created_at),
})))

function formatDateTime(value: string | null): string {
  if (!value) return '—'

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

async function loadNotifications(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<UserNotification>('/notifications', {
      page,
      filter: {
        status: statusFilter.value,
        type: typeFilter.value,
      },
    })

    notifications.value = response.data
    meta.value = response.meta as NotificationMeta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function runAction(id: number, action: 'mark-read' | 'acknowledge') {
  if (!canUpdate.value) {
    return
  }

  actionLoading.value = true
  errorMessage.value = null
  successMessage.value = null

  try {
    const response = await api.put<ApiResponse<UserNotification>>(`/notifications/${id}/${action}`)
    successMessage.value = response.data.message
    await loadNotifications(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    actionLoading.value = false
  }
}

onMounted(async () => {
  await loadNotifications()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Inbox"
      title="Notifications"
      description="Track approval prompts, governance decisions, and operational alerts in a tenant-scoped activity inbox."
    />

    <InlineAlert
      v-if="meta.unread_count"
      title="Unread notifications"
      :description="`${meta.unread_count} notifications still need review.`"
      tone="info"
    />

    <InlineAlert
      v-if="successMessage"
      title="Notification updated"
      :description="successMessage"
      tone="success"
    />

    <InlineAlert
      v-if="errorMessage"
      title="Unable to process notification inbox"
      :description="errorMessage"
      tone="danger"
    />

    <form @submit.prevent="loadNotifications(1)">
      <FilterBar>
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option v-for="option in userNotificationStatusOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="typeFilter"
          class="rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All notification types</option>
          <option v-for="option in userNotificationTypeOptions" :key="option.value" :value="option.value">
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
      empty-title="Inbox is clear"
      empty-description="Notifications will appear here when approvals, incidents, or inspection reviews require attention."
    >
      <template #cell-title="{ row }">
        <div class="space-y-1">
          <p class="font-semibold text-slate-900 dark:text-slate-100">{{ row.title }}</p>
          <p v-if="row.body" class="text-xs leading-5 text-slate-500 dark:text-slate-400">{{ row.body }}</p>
        </div>
      </template>
      <template #cell-type="{ value }">
        {{ String(value).replaceAll('_', ' ') }}
      </template>
      <template #cell-status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template
        v-if="canUpdate"
        #cell-actions="{ row }"
      >
        <div class="flex flex-wrap items-center gap-2">
          <RouterLink
            v-if="row.action_url"
            :to="String(row.action_url)"
            class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
          >
            Open
          </RouterLink>
          <button
            v-if="String(row.status) === 'unread'"
            type="button"
            class="rounded-xl border border-sky-300 dark:border-sky-800/60 px-3 py-1.5 text-xs font-semibold text-sky-700 dark:text-sky-200 transition hover:bg-sky-50 dark:hover:bg-sky-950/40"
            :disabled="actionLoading"
            @click="runAction(Number(row.id), 'mark-read')"
          >
            Mark read
          </button>
          <button
            v-if="String(row.status) !== 'acknowledged'"
            type="button"
            class="rounded-xl border border-emerald-300 dark:border-emerald-800/60 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-200 transition hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
            :disabled="actionLoading"
            @click="runAction(Number(row.id), 'acknowledge')"
          >
            Acknowledge
          </button>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadNotifications(meta.current_page - 1)"
      @next="loadNotifications(meta.current_page + 1)"
    />
  </div>
</template>
