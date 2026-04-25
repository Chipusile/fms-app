<script setup lang="ts">
defineProps<{
  title: string
  description: string
}>()
</script>

<template>
  <div class="rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 px-6 py-12 text-center">
    <div class="mx-auto max-w-xl space-y-2">
      <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
        {{ title }}
      </h3>
      <p class="text-sm leading-6 text-slate-600 dark:text-slate-400">
        {{ description }}
      </p>
      <div class="pt-2">
        <slot />
      </div>
    </div>
  </div>
</template>
