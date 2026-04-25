<script setup lang="ts">
import type { PaginationMeta } from '@/types'

defineProps<{
  meta: PaginationMeta
}>()

const emit = defineEmits<{
  previous: []
  next: []
}>()
</script>

<template>
  <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/70 px-4 py-3 shadow-sm shadow-slate-200/50 dark:shadow-black/20 sm:flex-row sm:items-center sm:justify-between">
    <p class="text-sm text-slate-600 dark:text-slate-400">
      Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} total records
    </p>

    <div class="flex items-center gap-2">
      <button
        type="button"
        class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-slate-800"
        :disabled="meta.current_page <= 1"
        @click="emit('previous')"
      >
        Previous
      </button>
      <button
        type="button"
        class="rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-slate-800"
        :disabled="meta.current_page >= meta.last_page"
        @click="emit('next')"
      >
        Next
      </button>
    </div>
  </div>
</template>
