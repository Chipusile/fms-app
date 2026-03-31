<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import { useAuthStore } from '@/stores/auth'
import type { ApiError } from '@/types'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const email = ref('admin@acme-transport.local')
const password = ref('password')
const submitting = ref(false)
const errorMessage = ref<string | null>(null)

const redirectName = computed(() => (route.query.redirect as string | undefined) ?? 'dashboard')

async function submit() {
  errorMessage.value = null
  submitting.value = true

  try {
    await auth.login(email.value, password.value)
    await router.push({ name: redirectName.value })
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="grid w-full max-w-6xl overflow-hidden rounded-[32px] border border-slate-200 bg-white/95 shadow-2xl shadow-slate-300/30 lg:grid-cols-[1.1fr_0.9fr]">
      <div class="hidden bg-slate-950 px-10 py-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div class="space-y-6">
          <span class="inline-flex rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-slate-200">
            Fleet Intelligence
          </span>
          <div class="space-y-4">
            <h1 class="max-w-xl text-4xl font-semibold leading-tight">
              Operate vehicles, people, compliance, and cost from one tenant-aware control plane.
            </h1>
            <p class="max-w-xl text-sm leading-7 text-slate-300">
              The foundation focuses on tenant isolation, policy-based access control, and reusable operational workflows rather than one-off screens.
            </p>
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
          <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Tenancy</p>
            <p class="mt-3 text-lg font-semibold">Strict isolation</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Security</p>
            <p class="mt-3 text-lg font-semibold">Policy enforced</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Delivery</p>
            <p class="mt-3 text-lg font-semibold">Phased rollout</p>
          </div>
        </div>
      </div>

      <div class="px-6 py-8 sm:px-10 sm:py-12">
        <div class="mx-auto max-w-md space-y-8">
          <div class="space-y-3">
            <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-blue-700">
              Sign In
            </span>
            <div>
              <h2 class="text-3xl font-semibold text-slate-950">
                Fleet Management System
              </h2>
              <p class="mt-2 text-sm leading-6 text-slate-600">
                Use one of the seeded tenant users to enter the Phase 1 platform shell.
              </p>
            </div>
          </div>

          <InlineAlert
            v-if="errorMessage"
            title="Authentication failed"
            :description="errorMessage"
            tone="danger"
          />

          <form class="space-y-5" @submit.prevent="submit">
            <div class="space-y-2">
              <label class="text-sm font-medium text-slate-700" for="email">Email</label>
              <input
                id="email"
                v-model="email"
                type="email"
                autocomplete="email"
                class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none ring-0 transition focus:border-blue-500"
              >
            </div>

            <div class="space-y-2">
              <label class="text-sm font-medium text-slate-700" for="password">Password</label>
              <input
                id="password"
                v-model="password"
                type="password"
                autocomplete="current-password"
                class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none ring-0 transition focus:border-blue-500"
              >
            </div>

            <button
              type="submit"
              class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="submitting"
            >
              {{ submitting ? 'Signing in...' : 'Access workspace' }}
            </button>
          </form>

          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600">
            Seeded example: <span class="font-semibold text-slate-900">admin@acme-transport.local / password</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
