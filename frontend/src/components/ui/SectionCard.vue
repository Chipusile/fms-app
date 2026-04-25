<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <section class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/70 p-5 shadow-sm shadow-slate-200/60 dark:shadow-black/20 backdrop-blur sm:p-6">
    <div class="mb-4 space-y-1">
      <h2 class="text-base font-semibold text-slate-950 dark:text-slate-100">
        {{ title }}
      </h2>
      <p
        v-if="description"
        class="text-sm leading-6 text-slate-600 dark:text-slate-400"
      >
        {{ description }}
      </p>
    </div>

    <slot />
  </section>
</template>
