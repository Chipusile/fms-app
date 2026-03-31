<script setup lang="ts">
defineProps<{
  title: string
  description?: string
  eyebrow?: string
}>()
</script>

<template>
  <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
    <div class="space-y-2">
      <p
        v-if="eyebrow"
        class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500"
      >
        {{ eyebrow }}
      </p>
      <div class="space-y-1">
        <h1 class="text-2xl font-semibold text-slate-950 sm:text-3xl">
          {{ title }}
        </h1>
        <p
          v-if="description"
          class="max-w-3xl text-sm leading-6 text-slate-600"
        >
          {{ description }}
        </p>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <slot name="actions" />
    </div>
  </div>
</template>
