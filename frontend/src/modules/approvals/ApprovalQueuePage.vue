<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import api from '@/plugins/axios'
import DataTable from '@/components/ui/DataTable.vue'
import FilterBar from '@/components/ui/FilterBar.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { approvalRequestStatusOptions, approvalRequestTypeOptions } from '@/lib/fleet-options'
import { listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type { ApiError, ApiResponse, ApprovalRequest, PaginationMeta } from '@/types'

const auth = useAuthStore()
const approvals = ref<ApprovalRequest[]>([])
const loading = ref(false)
const actionLoading = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const statusFilter = ref('')
const typeFilter = ref('')
const meta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const decisionTarget = ref<ApprovalRequest | null>(null)
const decisionAction = ref<'approve' | 'reject'>('approve')
const decisionNotes = ref('')

const canDecide = computed(() => auth.hasPermission('approvals.decide'))

const columns = computed(() => (
  canDecide.value
    ? [
        { key: 'title', label: 'Request' },
        { key: 'approval_type', label: 'Type' },
        { key: 'approvalable', label: 'Reference' },
        { key: 'requester', label: 'Requested by' },
        { key: 'status', label: 'Status' },
        { key: 'created_at', label: 'Submitted' },
        { key: 'actions', label: 'Actions' },
      ]
    : [
        { key: 'title', label: 'Request' },
        { key: 'approval_type', label: 'Type' },
        { key: 'approvalable', label: 'Reference' },
        { key: 'requester', label: 'Requested by' },
        { key: 'status', label: 'Status' },
        { key: 'created_at', label: 'Submitted' },
      ]
))

const rows = computed(() => approvals.value.map((approval) => ({
  id: approval.id,
  title: approval.title,
  approval_type: approval.approval_type,
  approvalable: approval.approvalable?.reference ?? 'Workflow item',
  requester: approval.requester?.name ?? 'Unknown requester',
  status: approval.status,
  created_at: formatDateTime(approval.created_at),
})))

function formatDateTime(value: string | null): string {
  if (!value) return '—'

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

async function loadApprovals(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<ApprovalRequest>('/approvals', {
      page,
      filter: {
        status: statusFilter.value,
        approval_type: typeFilter.value,
      },
    })

    approvals.value = response.data
    meta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

function beginDecision(approvalId: number, action: 'approve' | 'reject') {
  const approval = approvals.value.find((item) => item.id === approvalId)

  if (!approval) {
    return
  }

  decisionTarget.value = approval
  decisionAction.value = action
  decisionNotes.value = approval.decision_notes ?? ''
  successMessage.value = null
  errorMessage.value = null
}

function cancelDecision() {
  decisionTarget.value = null
  decisionNotes.value = ''
}

async function submitDecision() {
  if (!decisionTarget.value || !canDecide.value) {
    return
  }

  actionLoading.value = true
  errorMessage.value = null
  successMessage.value = null

  try {
    const endpoint = decisionAction.value === 'approve' ? 'approve' : 'reject'
    const response = await api.put<ApiResponse<ApprovalRequest>>(`/approvals/${decisionTarget.value.id}/${endpoint}`, {
      decision_notes: decisionNotes.value || null,
    })

    successMessage.value = response.data.message
    cancelDecision()
    await loadApprovals(meta.value.current_page)
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    actionLoading.value = false
  }
}

onMounted(async () => {
  await loadApprovals()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Governance"
      title="Approvals"
      description="Centralize inspection and incident review decisions in a single queue with tenant-aware approval permissions."
    />

    <InlineAlert
      v-if="successMessage"
      title="Approval queue updated"
      :description="successMessage"
      tone="success"
    />

    <InlineAlert
      v-if="errorMessage"
      title="Unable to process approval queue"
      :description="errorMessage"
      tone="danger"
    />

    <SectionCard
      v-if="decisionTarget"
      title="Decision draft"
      :description="`Preparing to ${decisionAction} ${decisionTarget.title}.`"
    >
      <div class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
          <p class="font-semibold text-slate-900">{{ decisionTarget.summary ?? 'Approval workflow record' }}</p>
          <p class="mt-1 text-xs text-slate-500">Reference: {{ decisionTarget.approvalable?.reference ?? 'Workflow item' }}</p>
        </div>
        <label class="space-y-2 text-sm text-slate-700">
          <span class="font-medium">Decision notes</span>
          <textarea
            v-model="decisionNotes"
            class="min-h-28 w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
            :disabled="actionLoading"
          />
        </label>
        <div class="flex flex-col gap-3 sm:flex-row">
          <button
            type="button"
            class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto"
            :disabled="actionLoading"
            @click="cancelDecision"
          >
            Cancel
          </button>
          <button
            type="button"
            class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
            :disabled="actionLoading"
            @click="submitDecision"
          >
            {{ actionLoading ? 'Submitting...' : decisionAction === 'approve' ? 'Approve request' : 'Reject request' }}
          </button>
        </div>
      </div>
    </SectionCard>

    <form @submit.prevent="loadApprovals(1)">
      <FilterBar>
        <select
          v-model="statusFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All statuses</option>
          <option v-for="option in approvalRequestStatusOptions" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <select
          v-model="typeFilter"
          class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-blue-500"
        >
          <option value="">All workflow types</option>
          <option v-for="option in approvalRequestTypeOptions" :key="option.value" :value="option.value">
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
      empty-title="No approval requests queued"
      empty-description="New governance workflows will appear here when inspections or incidents require review."
    >
      <template #cell-approval_type="{ value }">
        {{ String(value).replaceAll('_', ' ') }}
      </template>
      <template #cell-status="{ value }">
        <StatusBadge :value="String(value)" />
      </template>
      <template
        v-if="canDecide"
        #cell-actions="{ row }"
      >
        <div class="flex items-center gap-2">
          <button
            v-if="String(row.status) === 'pending'"
            type="button"
            class="rounded-xl border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50"
            @click="beginDecision(Number(row.id), 'approve')"
          >
            Approve
          </button>
          <button
            v-if="String(row.status) === 'pending'"
            type="button"
            class="rounded-xl border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
            @click="beginDecision(Number(row.id), 'reject')"
          >
            Reject
          </button>
          <span
            v-else
            class="text-xs text-slate-500"
          >
            Finalized
          </span>
        </div>
      </template>
    </DataTable>

    <PaginationBar
      :meta="meta"
      @previous="loadApprovals(meta.current_page - 1)"
      @next="loadApprovals(meta.current_page + 1)"
    />
  </div>
</template>
