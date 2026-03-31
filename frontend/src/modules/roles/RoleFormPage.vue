<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import { createResource, getResource, listResource, updateResource } from '@/lib/resource-client'
import type { ApiError, CreateRolePayload, Permission, Role, UpdateRolePayload } from '@/types'

const route = useRoute()
const router = useRouter()
const isEditMode = computed(() => Boolean(route.params.id))
const loading = ref(false)
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const permissions = ref<Permission[]>([])
const isSystemRole = ref(false)

const form = ref<CreateRolePayload>({
  name: '',
  slug: '',
  description: '',
  permission_ids: [],
})

const groupedPermissions = computed(() => {
  const groups = new Map<string, Permission[]>()

  permissions.value.forEach((permission) => {
    const existing = groups.get(permission.module) ?? []
    existing.push(permission)
    groups.set(permission.module, existing)
  })

  return Array.from(groups.entries())
})

async function loadPermissions() {
  const response = await listResource<Permission>('/permissions')
  permissions.value = response.data
}

async function loadRole() {
  if (!isEditMode.value) {
    return
  }

  const role = await getResource<Role>(`/roles/${route.params.id}`)
  isSystemRole.value = role.is_system
  form.value = {
    name: role.name,
    slug: role.slug,
    description: role.description ?? '',
    permission_ids: role.permissions?.map((permission) => permission.id) ?? [],
  }
}

async function submit() {
  submitting.value = true
  errorMessage.value = null
  fieldErrors.value = {}

  try {
    if (isSystemRole.value) {
      errorMessage.value = 'System roles are seeded authorization templates and cannot be edited from the UI.'
      return
    }

    if (isEditMode.value) {
      const payload: UpdateRolePayload = {
        name: form.value.name,
        slug: form.value.slug,
        description: form.value.description,
        permission_ids: form.value.permission_ids,
      }

      await updateResource<Role, UpdateRolePayload>(`/roles/${route.params.id}`, payload)
    } else {
      await createResource<Role, CreateRolePayload>('/roles', form.value)
    }

    await router.push({ name: 'roles' })
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
    await Promise.all([loadPermissions(), loadRole()])
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
      eyebrow="Access"
      :title="isEditMode ? 'Edit role' : 'Create role'"
      description="Permissions are grouped by module so tenant admins can create reusable access bundles without editing code."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'roles' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
        >
          Cancel
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to save role"
      :description="errorMessage"
      tone="danger"
    />

    <InlineAlert
      v-if="isSystemRole"
      title="System role"
      description="This role is part of the seeded authorization baseline and is intentionally read-only."
      tone="warning"
    />

    <div class="grid gap-6 xl:grid-cols-[0.85fr_1.15fr]">
      <SectionCard title="Role details" description="Basic metadata for the role container.">
        <div class="space-y-4">
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Role name</span>
            <input
              v-model="form.name"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || isSystemRole"
            >
            <FieldError :errors="errorsFor('name')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Role key</span>
            <input
              v-model="form.slug"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || isSystemRole"
            >
            <FieldError :errors="errorsFor('slug')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Description</span>
            <textarea
              v-model="form.description"
              class="min-h-32 w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading || isSystemRole"
            />
            <FieldError :errors="errorsFor('description')" />
          </label>
          <div class="flex flex-col gap-3 sm:flex-row">
            <RouterLink
              :to="{ name: 'roles' }"
              class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto"
            >
              Cancel
            </RouterLink>
            <button
              v-if="!isSystemRole"
              type="button"
              class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="loading || submitting"
              @click="submit"
            >
              {{ submitting ? 'Saving...' : isEditMode ? 'Save role' : 'Create role' }}
            </button>
          </div>
        </div>
      </SectionCard>

      <SectionCard title="Permission matrix" description="Permission names remain the stable authorization contract.">
        <div class="space-y-4">
          <div
            v-for="[group, modulePermissions] in groupedPermissions"
            :key="group"
            class="rounded-2xl border border-slate-200 p-4"
          >
            <h3 class="text-sm font-semibold capitalize text-slate-900">{{ group }}</h3>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
              <label
                v-for="permission in modulePermissions"
                :key="permission.id"
                class="flex items-center gap-3 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-700"
              >
                <input
                  v-model="form.permission_ids"
                  type="checkbox"
                  class="rounded border-slate-300"
                  :value="permission.id"
                  :disabled="loading || isSystemRole"
                >
                <span>{{ permission.slug }}</span>
              </label>
            </div>
            <FieldError :errors="errorsFor('permission_ids')" />
          </div>
        </div>
      </SectionCard>
    </div>
  </div>
</template>
