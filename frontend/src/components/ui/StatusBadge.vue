<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  value: string
}>()

const badgeClasses = computed(() => {
  const normalized = props.value.toLowerCase()

  if (normalized === 'active') {
    return 'border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-200'
  }

  if (['approved', 'completed', 'resolved', 'pass', 'acknowledged', 'valid'].includes(normalized)) {
    return 'border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-200'
  }

  if (['reviewed', 'read', 'under_review', 'in_progress', 'scheduled', 'converted', 'processing'].includes(normalized)) {
    return 'border-sky-200 dark:border-sky-900/60 bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-200'
  }

  if (
    normalized.startsWith('pending')
    || ['requested', 'submitted', 'requires_action', 'action_required', 'unread', 'medium', 'major', 'open', 'due_soon', 'expiring_soon', 'watch', 'queued'].includes(normalized)
  ) {
    return 'border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-200'
  }

  if (['suspended', 'rejected', 'cancelled', 'expired', 'flagged', 'fail', 'failed', 'high', 'critical', 'overdue', 'due_replacement'].includes(normalized)) {
    return 'border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-200'
  }

  if (['paused', 'inactive', 'closed', 'waived'].includes(normalized)) {
    return 'border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200'
  }

  return 'border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200'
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
