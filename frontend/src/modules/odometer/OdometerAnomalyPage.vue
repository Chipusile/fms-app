<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/plugins/axios'
import DataTable from '@/components/ui/DataTable.vue'
import FieldError from '@/components/ui/FieldError.vue'
import InlineAlert from '@/components/ui/InlineAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import SectionCard from '@/components/ui/SectionCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { createResource, getResource, listResource } from '@/lib/resource-client'
import { useAuthStore } from '@/stores/auth'
import type {
  ApiError,
  ManualOdometerReadingPayload,
  OdometerReading,
  OdometerSupportData,
  PaginationMeta,
  ReferenceOption,
  TripSupportVehicleOption,
} from '@/types'

const auth = useAuthStore()
const anomalies = ref<OdometerReading[]>([])
const history = ref<OdometerReading[]>([])
const vehicles = ref<TripSupportVehicleOption[]>([])
const drivers = ref<ReferenceOption[]>([])
const sources = ref<string[]>([])
const loading = ref(false)
const historyLoading = ref(false)
const submitting = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const anomalyMeta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const historyMeta = ref<PaginationMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const form = ref<ManualOdometerReadingPayload>({
  vehicle_id: 0,
  driver_id: null,
  reading: 0,
  recorded_at: new Date().toISOString().slice(0, 16),
  notes: '',
})

const selectedVehicle = computed(() => vehicles.value.find((vehicle) => vehicle.id === form.value.vehicle_id) ?? null)
const canCreate = computed(() => auth.hasPermission('odometer.create'))
const canResolve = computed(() => auth.hasPermission('odometer.update'))

const anomalyColumns = [
  { key: 'vehicle', label: 'Vehicle' },
  { key: 'reading', label: 'Reading' },
  { key: 'source', label: 'Source' },
  { key: 'recorded_at', label: 'Recorded at' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Actions' },
]

const historyColumns = [
  { key: 'recorded_at', label: 'Recorded at' },
  { key: 'reading', label: 'Reading' },
  { key: 'source', label: 'Source' },
  { key: 'status', label: 'Status' },
]

const anomalyRows = computed(() => anomalies.value.map((reading) => ({
  id: reading.id,
  vehicle: reading.vehicle?.registration_number ?? 'Unknown vehicle',
  reading: reading.reading.toLocaleString(),
  source: reading.source.replaceAll('_', ' '),
  recorded_at: formatDateTime(reading.recorded_at),
  status: reading.resolved_at ? 'resolved' : 'flagged',
})))

const historyRows = computed(() => history.value.map((reading) => ({
  id: reading.id,
  recorded_at: formatDateTime(reading.recorded_at),
  reading: reading.reading.toLocaleString(),
  source: reading.source.replaceAll('_', ' '),
  status: reading.is_anomaly ? (reading.resolved_at ? 'resolved' : 'flagged') : 'captured',
})))

function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

async function loadSupportData() {
  const data = await getResource<OdometerSupportData>('/odometer-readings/support-data')
  vehicles.value = data.vehicles
  drivers.value = data.drivers
  sources.value = data.sources

  if (!form.value.vehicle_id && vehicles.value[0]) {
    form.value.vehicle_id = vehicles.value[0].id
    form.value.reading = vehicles.value[0].odometer_reading
  }
}

async function loadAnomalies(page = 1) {
  loading.value = true
  errorMessage.value = null

  try {
    const response = await listResource<OdometerReading>('/odometer-readings/anomalies', { page })
    anomalies.value = response.data
    anomalyMeta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    loading.value = false
  }
}

async function loadHistory(page = 1) {
  if (!form.value.vehicle_id) {
    history.value = []
    historyMeta.value = { current_page: 1, last_page: 1, per_page: 15, total: 0 }
    return
  }

  historyLoading.value = true

  try {
    const response = await listResource<OdometerReading>(`/vehicles/${form.value.vehicle_id}/odometer-readings`, { page })
    history.value = response.data
    historyMeta.value = response.meta
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  } finally {
    historyLoading.value = false
  }
}

async function submitManualReading() {
  submitting.value = true
  errorMessage.value = null
  successMessage.value = null
  fieldErrors.value = {}

  try {
    await createResource<OdometerReading, ManualOdometerReadingPayload>('/odometer-readings', form.value)
    successMessage.value = 'Manual odometer reading recorded successfully.'
    await Promise.all([loadAnomalies(), loadHistory()])
  } catch (error) {
    const apiError = error as ApiError
    errorMessage.value = apiError.message
    fieldErrors.value = apiError.errors ?? {}
  } finally {
    submitting.value = false
  }
}

async function resolveAnomaly(id: number) {
  const resolutionNotes = globalThis.prompt('Resolution notes (optional)') ?? ''

  try {
    await api.put(`/odometer-readings/${id}/resolve-anomaly`, {
      resolution_notes: resolutionNotes || null,
    })
    successMessage.value = 'Odometer anomaly resolved successfully.'
    await Promise.all([loadAnomalies(anomalyMeta.value.current_page), loadHistory(historyMeta.value.current_page)])
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
}

function errorsFor(field: string): string[] | undefined {
  return fieldErrors.value[field]
}

onMounted(async () => {
  try {
    await loadSupportData()
    await Promise.all([loadAnomalies(), loadHistory()])
  } catch (error) {
    errorMessage.value = (error as ApiError).message
  }
})

watch(() => form.value.vehicle_id, async () => {
  if (selectedVehicle.value) {
    form.value.reading = selectedVehicle.value.odometer_reading
  }

  await loadHistory()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Operations"
      title="Odometer Control"
      description="Track mileage capture quality, resolve anomalies, and maintain a defensible odometer history for every fleet asset."
    />

    <InlineAlert
      v-if="successMessage"
      title="Odometer updated"
      :description="successMessage"
      tone="success"
    />

    <InlineAlert
      v-if="errorMessage"
      title="Unable to process odometer records"
      :description="errorMessage"
      tone="danger"
    />

    <div class="grid gap-6 xl:grid-cols-[0.92fr_1.08fr]">
      <SectionCard title="Manual reading" description="Use controlled manual capture when mileage is collected outside automated workflow sources.">
        <form class="space-y-4 text-sm text-slate-700" @submit.prevent="submitManualReading">
          <label class="space-y-2">
            <span class="font-medium">Vehicle</span>
            <select v-model="form.vehicle_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="!canCreate">
              <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                {{ vehicle.label }}{{ vehicle.secondary ? ` · ${vehicle.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('vehicle_id')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Driver</span>
            <select v-model="form.driver_id" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="!canCreate">
              <option :value="null">No driver linked</option>
              <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                {{ driver.label }}{{ driver.secondary ? ` · ${driver.secondary}` : '' }}
              </option>
            </select>
            <FieldError :errors="errorsFor('driver_id')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Reading</span>
            <input v-model.number="form.reading" type="number" min="0" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :placeholder="selectedVehicle ? `Current ${selectedVehicle.odometer_reading}` : 'Mileage'" :disabled="!canCreate">
            <FieldError :errors="errorsFor('reading')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Recorded at</span>
            <input v-model="form.recorded_at" type="datetime-local" class="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="!canCreate">
            <FieldError :errors="errorsFor('recorded_at')" />
          </label>
          <label class="space-y-2">
            <span class="font-medium">Notes</span>
            <textarea v-model="form.notes" class="min-h-24 w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-blue-500" :disabled="!canCreate" />
            <FieldError :errors="errorsFor('notes')" />
          </label>
          <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600">
            Supported sources in this tenant: {{ sources.join(', ').replaceAll('_', ' ') }}.
          </div>
          <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="!canCreate || submitting">
            {{ submitting ? 'Recording...' : 'Record manual reading' }}
          </button>
        </form>
      </SectionCard>

      <SectionCard title="Vehicle history" description="Review the current vehicle odometer trail alongside anomaly resolution status.">
        <DataTable
          :columns="historyColumns"
          :rows="historyRows"
          :loading="historyLoading"
          empty-title="No odometer history yet"
          empty-description="Select a vehicle and begin capturing readings through trips, fuel logs, or manual entries."
        >
          <template #cell-status="{ value }">
            <StatusBadge :value="String(value)" />
          </template>
        </DataTable>

        <div class="mt-4">
          <PaginationBar
            :meta="historyMeta"
            @previous="loadHistory(historyMeta.current_page - 1)"
            @next="loadHistory(historyMeta.current_page + 1)"
          />
        </div>
      </SectionCard>
    </div>

    <SectionCard title="Flagged anomalies" description="Resolve regressed or unusually large jumps before they distort downstream reporting.">
      <DataTable
        :columns="anomalyColumns"
        :rows="anomalyRows"
        :loading="loading"
        empty-title="No unresolved anomalies"
        empty-description="Current odometer readings are within expected bounds."
      >
        <template #cell-status="{ value }">
          <StatusBadge :value="String(value)" />
        </template>
        <template #cell-actions="{ row }">
          <button
            v-if="canResolve"
            type="button"
            class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
            @click="resolveAnomaly(Number(row.id))"
          >
            Resolve
          </button>
          <span v-else class="text-xs text-slate-500">Read only</span>
        </template>
      </DataTable>

      <div class="mt-4">
        <PaginationBar
          :meta="anomalyMeta"
          @previous="loadAnomalies(anomalyMeta.current_page - 1)"
          @next="loadAnomalies(anomalyMeta.current_page + 1)"
        />
      </div>
    </SectionCard>
  </div>
</template>
