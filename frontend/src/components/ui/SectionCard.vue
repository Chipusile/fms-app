<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <section class="rounded-2xl border border-slate-200 bg-white/90 p-5 shadow-sm shadow-slate-200/60 backdrop-blur sm:p-6">
    <div class="mb-4 space-y-1">
      <h2 class="text-base font-semibold text-slate-950">
        {{ title }}
      </h2>
      <p
        v-if="description"
        class="text-sm leading-6 text-slate-600"
      >
        {{ description }}
      </p>
    </div>

    <slot />
  </section>
</template>
