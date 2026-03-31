<script setup lang="ts">
defineProps<{
  errors?: string[]
}>()
</script>

<template>
  <p
    v-if="errors?.length"
    class="text-xs font-medium text-rose-600"
  >
    {{ errors[0] }}
  </p>
</template>
