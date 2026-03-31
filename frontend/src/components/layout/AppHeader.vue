<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const emit = defineEmits<{
  toggleSidebar: []
}>()

const auth = useAuthStore()
const router = useRouter()
const dropdownOpen = ref(false)

function closeDropdown() {
  globalThis.setTimeout(() => {
    dropdownOpen.value = false
  }, 150)
}

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <header class="flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 sm:px-6">
    <!-- Mobile menu button -->
    <button
      class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 lg:hidden"
      type="button"
      aria-label="Toggle navigation"
      @click="emit('toggleSidebar')"
    >
      <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>

    <!-- Page title area (can be enhanced with breadcrumbs) -->
    <div class="hidden lg:block" />

    <!-- User dropdown -->
    <div class="relative">
      <button
        class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
        type="button"
        aria-haspopup="menu"
        :aria-expanded="dropdownOpen ? 'true' : 'false'"
        @click="dropdownOpen = !dropdownOpen"
        @blur="closeDropdown"
      >
        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-primary-700 text-xs font-semibold">
          {{ auth.user?.name?.charAt(0)?.toUpperCase() }}
        </div>
        <span class="hidden sm:block">{{ auth.user?.name }}</span>
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <!-- Dropdown menu -->
      <div
        v-if="dropdownOpen"
        role="menu"
        class="absolute right-0 mt-2 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
      >
        <div class="border-b border-gray-100 px-4 py-2">
          <p class="text-sm font-medium text-gray-900">{{ auth.user?.name }}</p>
          <p class="text-xs text-gray-500">{{ auth.user?.email }}</p>
        </div>
        <button
          class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
          type="button"
          role="menuitem"
          @click="handleLogout"
        >
          Sign out
        </button>
      </div>
    </div>
  </header>
</template>
