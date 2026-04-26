<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import api, { initCsrf } from '@/plugins/axios'
import type { ApiError } from '@/types'

const tenantName = ref('')
const name = ref('')
const email = ref('')
const phone = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)

async function submit() {
  errorMessage.value = null
  successMessage.value = null
  submitting.value = true

  try {
    await initCsrf()
    const response = await api.post('/auth/register', {
      tenant_name: tenantName.value,
      name: name.value,
      email: email.value,
      phone: phone.value || null,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })

    successMessage.value = response.data.message
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-xl rounded-3xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-300/20 dark:border-slate-800 dark:bg-slate-900">
      <div class="mb-8">
        <h1 class="text-2xl font-semibold text-slate-950 dark:text-slate-100">Create workspace</h1>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Register a tenant and the first administrator account.</p>
      </div>

      <InlineAlert v-if="errorMessage" title="Registration failed" :description="errorMessage" tone="danger" class="mb-5" />
      <InlineAlert v-if="successMessage" title="Check your email" :description="successMessage" tone="success" class="mb-5" />

      <form class="space-y-4" @submit.prevent="submit">
        <input v-model="tenantName" required placeholder="Organisation name" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
        <input v-model="name" required placeholder="Your name" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
        <input v-model="email" required type="email" placeholder="Email" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
        <input v-model="phone" placeholder="Phone" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
        <input v-model="password" required type="password" autocomplete="new-password" placeholder="Password" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
        <input v-model="passwordConfirmation" required type="password" autocomplete="new-password" placeholder="Confirm password" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">

        <button type="submit" :disabled="submitting" class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60">
          {{ submitting ? 'Creating...' : 'Create workspace' }}
        </button>
      </form>

      <RouterLink to="/login" class="mt-6 inline-flex text-sm font-medium text-blue-700 dark:text-blue-300">Back to sign in</RouterLink>
    </div>
  </div>
</template>
