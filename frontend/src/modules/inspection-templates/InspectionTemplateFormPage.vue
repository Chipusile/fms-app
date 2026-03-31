<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import {
  inspectionResponseTypeOptions,
  inspectionTemplateAppliesToOptions,
  inspectionTemplateStatusOptions,
} from '@/lib/fleet-options'
import { createResource, getResource, updateResource } from '@/lib/resource-client'
import type {
  ApiError,
  InspectionTemplate,
  InspectionTemplateItemPayload,
  InspectionTemplatePayload,
} from '@/types'

const route = useRoute()
const router = useRouter()
const isEditMode = computed(() => Boolean(route.params.id))
const loading = ref(false)
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})

const form = ref<InspectionTemplatePayload>({
  name: '',
  code: '',
  description: '',
  applies_to: 'vehicle',
  status: 'active',
  requires_review_on_critical: true,
  items: [makeItem()],
})

function makeItem(overrides: Partial<InspectionTemplateItemPayload> = {}): InspectionTemplateItemPayload {
  return {
    title: '',
    description: '',
    response_type: 'pass_fail',
    is_required: true,
    triggers_defect_on_fail: true,
    sort_order: null,
    ...overrides,
  }
}

async function loadTemplate() {
  if (!isEditMode.value) {
    return
  }

  const template = await getResource<InspectionTemplate>(`/inspection-templates/${route.params.id}`)
  form.value = {
    name: template.name,
    code: template.code,
    description: template.description ?? '',
    applies_to: template.applies_to,
    status: template.status,
    requires_review_on_critical: template.requires_review_on_critical,
    items: (template.items ?? []).map((item) => makeItem({
      title: item.title,
      description: item.description ?? '',
      response_type: item.response_type,
      is_required: item.is_required,
      triggers_defect_on_fail: item.triggers_defect_on_fail,
      sort_order: item.sort_order,
    })),
  }
}

function addItem() {
  form.value.items.push(makeItem({ sort_order: form.value.items.length + 1 }))
}

function removeItem(index: number) {
  if (form.value.items.length <= 1) {
    return
  }

  form.value.items.splice(index, 1)
}

async function submit() {
  submitting.value = true
  errorMessage.value = null
  fieldErrors.value = {}

  const payload: InspectionTemplatePayload = {
    ...form.value,
    code: form.value.code.trim().toUpperCase(),
    items: form.value.items.map((item, index) => ({
      ...item,
      sort_order: index + 1,
    })),
  }

  try {
    if (isEditMode.value) {
      await updateResource<InspectionTemplate, InspectionTemplatePayload>(`/inspection-templates/${route.params.id}`, payload)
    } else {
      await createResource<InspectionTemplate, InspectionTemplatePayload>('/inspection-templates', payload)
    }

    await router.push({ name: 'inspection-templates' })
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
    await loadTemplate()
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
      eyebrow="Operations"
      :title="isEditMode ? 'Edit inspection template' : 'Create inspection template'"
      description="Templates let each tenant define repeatable inspection checklists and review triggers without branching the codebase."
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'inspection-templates' }"
          class="inline-flex items-center rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
        >
          Back to templates
        </RouterLink>
      </template>
    </PageHeader>

    <InlineAlert
      v-if="errorMessage"
      title="Unable to save inspection template"
      :description="errorMessage"
      tone="danger"
    />

    <form class="grid gap-6 xl:grid-cols-[1fr_0.92fr]" @submit.prevent="submit">
      <SectionCard title="Template details" description="Define the reusable checklist identity and publication status.">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Template name</span>
            <input
              v-model="form.name"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading"
            >
            <FieldError :errors="errorsFor('name')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Code</span>
            <input
              v-model="form.code"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 uppercase outline-none focus:border-blue-500"
              :disabled="loading"
            >
            <FieldError :errors="errorsFor('code')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Applies to</span>
            <select
              v-model="form.applies_to"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading"
            >
              <option
                v-for="option in inspectionTemplateAppliesToOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('applies_to')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700">
            <span class="font-medium">Status</span>
            <select
              v-model="form.status"
              class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading"
            >
              <option
                v-for="option in inspectionTemplateStatusOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
            <FieldError :errors="errorsFor('status')" />
          </label>
          <label class="space-y-2 text-sm text-slate-700 md:col-span-2">
            <span class="font-medium">Description</span>
            <textarea
              v-model="form.description"
              class="min-h-28 w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
              :disabled="loading"
            />
            <FieldError :errors="errorsFor('description')" />
          </label>
        </div>
      </SectionCard>

      <SectionCard title="Governance" description="Control whether critical defects route into the approval queue.">
        <div class="space-y-4 text-sm text-slate-700">
          <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <input
              v-model="form.requires_review_on_critical"
              type="checkbox"
              class="h-4 w-4 rounded border-slate-300"
              :disabled="loading"
            >
            <span class="font-medium">Require review when critical defects are recorded</span>
          </label>

          <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600">
            Keep templates inactive until the checklist structure has been reviewed. Active templates become available to inspection officers immediately.
          </div>

          <div class="flex flex-col gap-3 sm:flex-row">
            <RouterLink
              :to="{ name: 'inspection-templates' }"
              class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto"
            >
              Cancel
            </RouterLink>
            <button
              type="submit"
              class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="loading || submitting"
            >
              {{ submitting ? 'Saving...' : isEditMode ? 'Save template' : 'Create template' }}
            </button>
          </div>
        </div>
      </SectionCard>
    </form>

    <SectionCard title="Checklist items" description="Each row becomes a structured inspection response and audit trail entry.">
      <div class="space-y-4">
        <div
          v-for="(item, index) in form.items"
          :key="`item-${index}`"
          class="rounded-3xl border border-slate-200 bg-slate-50/70 p-5"
        >
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="text-sm font-semibold text-slate-900">Item {{ index + 1 }}</p>
              <p class="text-xs text-slate-500">Structured response settings and defect behavior.</p>
            </div>
            <button
              type="button"
              class="rounded-xl border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="form.items.length <= 1"
              @click="removeItem(index)"
            >
              Remove
            </button>
          </div>

          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="space-y-2 text-sm text-slate-700 md:col-span-2">
              <span class="font-medium">Prompt</span>
              <input
                v-model="item.title"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
                :disabled="loading"
              >
              <FieldError :errors="errorsFor(`items.${index}.title`)" />
            </label>
            <label class="space-y-2 text-sm text-slate-700">
              <span class="font-medium">Response type</span>
              <select
                v-model="item.response_type"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
                :disabled="loading"
              >
                <option
                  v-for="option in inspectionResponseTypeOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>
              <FieldError :errors="errorsFor(`items.${index}.response_type`)" />
            </label>
            <label class="space-y-2 text-sm text-slate-700">
              <span class="font-medium">Description</span>
              <input
                v-model="item.description"
                class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500"
                :disabled="loading"
              >
              <FieldError :errors="errorsFor(`items.${index}.description`)" />
            </label>
          </div>

          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
              <input
                v-model="item.is_required"
                type="checkbox"
                class="h-4 w-4 rounded border-slate-300"
                :disabled="loading"
              >
              <span class="font-medium">Response is required</span>
            </label>
            <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
              <input
                v-model="item.triggers_defect_on_fail"
                type="checkbox"
                class="h-4 w-4 rounded border-slate-300"
                :disabled="loading || item.response_type !== 'pass_fail'"
              >
              <span class="font-medium">Failure can trigger a defect</span>
            </label>
          </div>
        </div>

        <div class="flex justify-start">
          <button
            type="button"
            class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            @click="addItem"
          >
            Add checklist item
          </button>
        </div>
      </div>
    </SectionCard>
  </div>
</template>
