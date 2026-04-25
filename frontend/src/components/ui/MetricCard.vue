<script setup lang="ts">
withDefaults(defineProps<{
  label: string
  value: string | number
  hint?: string
  tone?: 'default' | 'success' | 'warning' | 'danger' | 'info'
}>(), {
  tone: 'default',
})

const toneClasses = {
  default: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200',
  success: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-200',
  warning: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-200',
  danger: 'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-200',
  info: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-200',
}
</script>

<template>
  <article class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/70 p-5 shadow-sm shadow-slate-200/60 dark:shadow-black/20">
    <div class="flex items-start justify-between gap-3">
      <div>
        <p class="text-sm font-medium text-slate-600 dark:text-slate-400">
          {{ label }}
        </p>
        <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 dark:text-slate-100">
          {{ value }}
        </p>
      </div>
      <span :class="['rounded-full px-3 py-1 text-xs font-semibold', toneClasses[tone]]">
        {{ tone }}
      </span>
    </div>
    <p
      v-if="hint"
      class="mt-4 text-sm leading-6 text-slate-500 dark:text-slate-400"
    >
      {{ hint }}
    </p>
  </article>
</template>
