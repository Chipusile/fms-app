<script setup lang="ts">
type TableColumn = {
  key: string
  label: string
}

type TableRow = Record<string, unknown>

defineProps<{
  columns: TableColumn[]
  rows: TableRow[]
  loading?: boolean
  emptyTitle?: string
  emptyDescription?: string
}>()

function formatCell(value: unknown): string {
  if (value === null || value === undefined || value === '') {
    return '—'
  }

  if (typeof value === 'boolean') {
    return value ? 'Yes' : 'No'
  }

  return String(value)
}
</script>

<template>
  <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white/90 shadow-sm shadow-slate-200/60">
    <template v-if="loading">
      <div class="px-6 py-12 text-center text-sm text-slate-500">
        Loading records...
      </div>
    </template>

    <template v-else-if="rows.length > 0">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th
                v-for="column in columns"
                :key="column.key"
                class="px-4 py-3 text-left font-semibold text-slate-600"
              >
                {{ column.label }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="(row, rowIndex) in rows"
              :key="String(row.id ?? rowIndex)"
              class="hover:bg-slate-50/60"
            >
              <td
                v-for="column in columns"
                :key="column.key"
                class="px-4 py-3 text-slate-700"
              >
                <slot
                  :name="`cell-${column.key}`"
                  :row="row"
                  :value="row[column.key]"
                  :row-index="rowIndex"
                >
                  {{ formatCell(row[column.key]) }}
                </slot>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <template v-else>
      <div class="px-6 py-12 text-center">
        <h3 class="text-base font-semibold text-slate-900">
          {{ emptyTitle ?? 'No records available' }}
        </h3>
        <p
          v-if="emptyDescription"
          class="mt-2 text-sm leading-6 text-slate-600"
        >
          {{ emptyDescription }}
        </p>
      </div>
    </template>
  </div>
</template>
