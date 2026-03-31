<script setup lang="ts">
defineProps<{
  title: string
  description: string
}>()
</script>

<template>
  <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
    <div class="mx-auto max-w-xl space-y-2">
      <h3 class="text-lg font-semibold text-slate-900">
        {{ title }}
      </h3>
      <p class="text-sm leading-6 text-slate-600">
        {{ description }}
      </p>
      <div class="pt-2">
        <slot />
      </div>
    </div>
  </div>
</template>
