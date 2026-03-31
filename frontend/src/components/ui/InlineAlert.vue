<script setup lang="ts">
withDefaults(defineProps<{
  title: string
  description?: string
  tone?: 'info' | 'warning' | 'danger' | 'success'
}>(), {
  tone: 'info',
})

const toneClasses = {
  info: 'border-blue-200 bg-blue-50 text-blue-900',
  warning: 'border-amber-200 bg-amber-50 text-amber-900',
  danger: 'border-rose-200 bg-rose-50 text-rose-900',
  success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
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
