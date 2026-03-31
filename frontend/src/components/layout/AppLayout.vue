<script setup lang="ts">
import { ref } from 'vue'
import AppSidebar from './AppSidebar.vue'
import AppHeader from './AppHeader.vue'

const sidebarOpen = ref(false)
</script>

<template>
  <div class="flex h-screen bg-gray-50">
    <a
      href="#app-main-content"
      class="sr-only absolute left-4 top-4 z-[60] rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white focus:not-sr-only"
    >
      Skip to main content
    </a>

    <!-- Mobile sidebar backdrop -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-40 bg-black/50 lg:hidden"
      @click="sidebarOpen = false"
    />

    <!-- Sidebar -->
    <AppSidebar
      :open="sidebarOpen"
      @close="sidebarOpen = false"
    />

    <!-- Main content -->
    <div class="flex flex-1 flex-col overflow-hidden lg:ml-64">
      <AppHeader @toggle-sidebar="sidebarOpen = !sidebarOpen" />

      <main
        id="app-main-content"
        tabindex="-1"
        class="flex-1 overflow-y-auto p-4 sm:p-6"
      >
        <RouterView />
      </main>
    </div>
  </div>
</template>
