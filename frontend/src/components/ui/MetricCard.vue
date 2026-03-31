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
  default: 'bg-slate-100 text-slate-700',
  success: 'bg-emerald-100 text-emerald-700',
  warning: 'bg-amber-100 text-amber-700',
  danger: 'bg-rose-100 text-rose-700',
  info: 'bg-blue-100 text-blue-700',
}
</script>

<template>
  <article class="rounded-2xl border border-slate-200 bg-white/90 p-5 shadow-sm shadow-slate-200/60">
    <div class="flex items-start justify-between gap-3">
      <div>
        <p class="text-sm font-medium text-slate-600">
          {{ label }}
        </p>
        <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">
          {{ value }}
        </p>
      </div>
      <span :class="['rounded-full px-3 py-1 text-xs font-semibold', toneClasses[tone]]">
        {{ tone }}
      </span>
    </div>
    <p
      v-if="hint"
      class="mt-4 text-sm leading-6 text-slate-500"
    >
      {{ hint }}
    </p>
  </article>
</template>
