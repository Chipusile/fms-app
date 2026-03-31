<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  value: string
}>()

const badgeClasses = computed(() => {
  const normalized = props.value.toLowerCase()

  if (normalized === 'active') {
    return 'border-emerald-200 bg-emerald-50 text-emerald-700'
  }

  if (['approved', 'completed', 'resolved', 'pass', 'acknowledged', 'valid'].includes(normalized)) {
    return 'border-emerald-200 bg-emerald-50 text-emerald-700'
  }

  if (['reviewed', 'read', 'under_review', 'in_progress', 'scheduled', 'converted', 'processing'].includes(normalized)) {
    return 'border-sky-200 bg-sky-50 text-sky-700'
  }

  if (
    normalized.startsWith('pending')
    || ['requested', 'submitted', 'requires_action', 'action_required', 'unread', 'medium', 'major', 'open', 'due_soon', 'expiring_soon', 'watch', 'queued'].includes(normalized)
  ) {
    return 'border-amber-200 bg-amber-50 text-amber-700'
  }

  if (['suspended', 'rejected', 'cancelled', 'expired', 'flagged', 'fail', 'failed', 'high', 'critical', 'overdue', 'due_replacement'].includes(normalized)) {
    return 'border-rose-200 bg-rose-50 text-rose-700'
  }

  if (['paused', 'inactive', 'closed', 'waived'].includes(normalized)) {
    return 'border-slate-200 bg-slate-100 text-slate-700'
  }

  return 'border-slate-200 bg-slate-100 text-slate-700'
})

const label = computed(() => props.value.replaceAll('_', ' '))
</script>

<template>
  <span
    :class="[
      'inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold capitalize',
      badgeClasses,
    ]"
  >
    {{ label }}
  </span>
</template>
