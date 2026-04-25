<script setup lang="ts">
withDefaults(defineProps<{
  title: string
  description?: string
  tone?: 'info' | 'warning' | 'danger' | 'success'
}>(), {
  tone: 'info',
})

const toneClasses = {
  info: 'border-blue-200 dark:border-blue-900/60 bg-blue-50 dark:bg-blue-950/40 text-blue-900 dark:text-blue-100',
  warning: 'border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-100',
  danger: 'border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/40 text-rose-900 dark:text-rose-100',
  success: 'border-emerald-200 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-900 dark:text-emerald-100',
}
</script>

<template>
  <div :class="['rounded-2xl border px-4 py-3', toneClasses[tone]]">
    <p class="text-sm font-semibold">
      {{ title }}
    </p>
    <p
      v-if="description"
      class="mt-1 text-sm leading-6 opacity-90"
    >
      {{ description }}
    </p>
  </div>
</template>
