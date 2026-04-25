<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import { userStatusOptions } from '@/lib/options'
import { createResource, getResource, listResource, updateResource } from '@/lib/resource-client'
import type { ApiError, CreateUserPayload, Role, UpdateUserPayload, User, UserStatus } from '@/types'

const route = useRoute()
const router = useRouter()
const isEditMode = computed(() => Boolean(route.params.id))
const loading = ref(false)
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const roleOptions = ref<Role[]>([])

const form = ref<CreateUserPayload>({
  name: '',
  email: '',
  password: '',
  phone: '',
  status: 'active',
  role_ids: [],
})

async function loadRoleOptions() {
  const response = await listResource<Role>('/roles', { per_page: 100 })
  roleOptions.value = response.data
}

async function loadUser() {
  if (!isEditMode.value) {
    return
  }

  const user = await getResource<User>(`/users/${route.params.id}`)
  form.value = {
    name: user.name,
    email: user.email,
    password: '',
    phone: user.phone ?? '',
    status: user.status,
    role_ids: user.roles?.map((role) => role.id) ?? [],
  }
}

async function submit() {
  submitting.value = true
  errorMessage.value = null
  fieldErrors.value = {}

  try {
    if (isEditMode.value) {
      const payload: UpdateUserPayload = {
        name: form.value.name,
        email: form.value.email,
        phone: form.value.phone || undefined,
        status: form.value.status as UserStatus,
        role_ids: form.value.role_ids,
      }

      if (form.value.password) {
        payload.password = form.value.password
      }

      await updateResource<User, UpdateUserPayload>(`/users/${route.params.id}`, payload)
    } else {
      await createResource<User, CreateUserPayload>('/users', form.value)
    }

    await router.push({ name: 'users' })
  } catch (error) {
    const apiError = error as ApiError
    errorMessage.value = apiError.message
    fieldErrors.value = apiError.errors ?? {}
  } finally {
    submitting.value = false
  }
}

function errorsFor(field: string): string[] | undefined {
  return fieldErrors.value[field]
}

onMounted(async () => {
  loading.value = true

  try {
    await Promise.all([loadRoleOptions(), loadUser()])
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Identity"
      :title="isEditMode ? 'Edit user' : 'Create user'"
      description="The shared form structure below is the Phase 1 baseline for accessible forms, grouped sections, and future backend binding."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'users' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50"
        >
          Cancel
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to save user"
      :description="errorMessage"
      tone="danger"
    />

    <form
      class="grid gap-6 xl:grid-cols-[1fr_0.9fr]"
      @submit.prevent="submit"
    >
      <SectionCard
        title="Profile details"
        description="Core identity and communication fields."
      >
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Full name</span>
            <input
              v-model="form.name"
              class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading"
            >
            <FieldError :errors="errorsFor('name')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Email</span>
            <input
              v-model="form.email"
              type="email"
              class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading"
            >
            <FieldError :errors="errorsFor('email')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Phone</span>
            <input
              v-model="form.phone"
              class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading"
            >
            <FieldError :errors="errorsFor('phone')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Status</span>
            <select
              v-model="form.status"
              class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading"
            >
              <option
                v-for="option in userStatusOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('status')" />
          </label>
        </div>
      </SectionCard>

      <SectionCard
        title="Access configuration"
        description="Assign roles and define account behavior."
      >
        <div class="space-y-4">
          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">Roles</span>
            <div class="grid gap-2">
              <label
                v-for="role in roleOptions"
                :key="role.id"
                class="flex items-center gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm text-slate-700 dark:text-slate-200"
              >
                <input
                  v-model="form.role_ids"
                  type="checkbox"
                  :value="role.id"
                  class="rounded border-slate-300 dark:border-slate-700"
                  :disabled="loading"
                >
                <span>{{ role.name }}</span>
              </label>
            </div>
            <FieldError :errors="errorsFor('role_ids')" />
          </label>

          <label class="space-y-2 text-sm text-slate-700 dark:text-slate-200">
            <span class="font-medium">{{ isEditMode ? 'Reset password' : 'Temporary password' }}</span>
            <input
              v-model="form.password"
              type="password"
              class="w-full rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading"
            >
            <FieldError :errors="errorsFor('password')" />
          </label>

          <div class="rounded-2xl bg-slate-50 dark:bg-slate-900/50 p-4 text-sm leading-6 text-slate-600 dark:text-slate-400">
            This form is now wired to the tenant-scoped roles endpoint and user create/update API.
          </div>

          <div class="flex flex-col gap-3 sm:flex-row">
            <RouterLink
              :to="{ name: 'users' }"
              class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 dark:border-slate-700 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-900/50 sm:w-auto"
            >
              Cancel
            </RouterLink>
            <button
              type="submit"
              class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="loading || submitting"
            >
              {{ submitting ? 'Saving...' : isEditMode ? 'Save changes' : 'Create user' }}
            </button>
          </div>
        </div>
      </SectionCard>
    </form>
  </div>
</template>
