<script setup lang="ts">
import { onMounted, ref } from 'vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import { getResource } from '@/lib/resource-client'
import type { ApiError, BulkImportTemplate } from '@/types'

const loading = ref(false)
const errorMessage = ref<string | null>(null)
const templates = ref<BulkImportTemplate[]>([])

async function loadTemplates() {
  loading.value = true
  errorMessage.value = null

  try {
    templates.value = await getResource<BulkImportTemplate[]>('/import-templates')
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

function downloadTemplate(template: BulkImportTemplate) {
  const blob = new Blob([template.csv_template], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = url
  link.download = template.filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

onMounted(async () => {
  await loadTemplates()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Onboarding"
      title="Bulk onboarding templates"
      description="Phase 2 stops short of executing spreadsheet imports, but the import contract is now explicit and downloadable for vehicles and drivers."
    />

    <InlineAlert
      v-if="errorMessage"
      title="Unable to load onboarding templates"
      :description="errorMessage"
      tone="danger"
    />

    <div v-if="loading" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/70 px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400 shadow-sm shadow-slate-200/60 dark:shadow-black/20">
      Loading templates...
    </div>

    <div v-else class="grid gap-6 xl:grid-cols-2">
      <SectionCard
        v-for="template in templates"
        :key="template.resource"
        :title="template.label"
        :description="template.description"
      >
        <div class="space-y-5 text-sm text-slate-700 dark:text-slate-200">
          <div class="flex flex-wrap gap-2">
            <span
              v-for="column in template.columns"
              :key="column"
              class="rounded-full border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-3 py-1 text-xs font-semibold text-slate-600 dark:text-slate-400"
            >
              {{ column }}
            </span>
          </div>

          <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Sample row</p>
            <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 p-4">
              <table class="min-w-full text-left text-xs text-slate-600 dark:text-slate-400">
                <thead>
                  <tr>
                    <th v-for="column in template.columns" :key="column" class="px-2 py-2 font-semibold text-slate-700 dark:text-slate-200">
                      {{ column }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td v-for="column in template.columns" :key="column" class="px-2 py-2 whitespace-nowrap">
                      {{ template.sample_row[column] ?? '—' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Import notes</p>
            <ul class="space-y-2 text-sm leading-6 text-slate-600 dark:text-slate-400">
              <li v-for="note in template.notes" :key="note">
                {{ note }}
              </li>
            </ul>
          </div>

          <button
            type="button"
            class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
            @click="downloadTemplate(template)"
          >
            Download CSV template
          </button>
        </div>
      </SectionCard>
    </div>
  </div>
</template>
