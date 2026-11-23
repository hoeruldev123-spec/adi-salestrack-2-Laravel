<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { forecastsApi, type ForecastConfiguration } from '@/api/forecasts'
import { useForecastStore } from '@/stores/forecast'
import { useUiStore } from '@/stores/ui'
import { getApiErrorMessage, getFieldError } from '@/utils/form'
import { FORECAST_TYPES } from '@/constants'

const router = useRouter()
const forecastStore = useForecastStore()
const ui = useUiStore()

const loadingConfig = ref(true)
const saving = ref(false)
const configuration = ref<ForecastConfiguration | null>(null)
const fieldErrors = reactive<Record<string, string>>({})

const form = reactive({
  forecast_name: '',
  forecast_type: 'OPPORTUNITY',
  historical_start_date: '',
  historical_end_date: '',
  forecast_start_date: '',
  forecast_end_date: '',
  principal_id: '' as number | '',
  revenue_category: 'ALL',
  simulation_count: 10000,
  target_revenue: null as number | null,
})

const simCountLabel = computed(() => form.simulation_count.toLocaleString('id-ID'))
const simulationMin = computed(() => configuration.value?.simulation.min ?? 100)
const simulationMax = computed(() => configuration.value?.simulation.max ?? 1000000)

function dayBefore(isoDate: string): string {
  const date = new Date(`${isoDate}T00:00:00`)
  date.setDate(date.getDate() - 1)
  return date.toISOString().slice(0, 10)
}

async function loadConfiguration() {
  loadingConfig.value = true
  try {
    const config = await forecastsApi.configuration()
    configuration.value = config
    const forecastStart = config.forecast_period.suggested_start
    const availableEnd = config.historical_period.available_end
    form.historical_start_date = config.historical_period.available_start ?? ''
    form.historical_end_date = availableEnd && availableEnd < forecastStart ? availableEnd : dayBefore(forecastStart)
    form.forecast_start_date = forecastStart
    form.forecast_end_date = config.forecast_period.suggested_end
    form.simulation_count = config.simulation.default
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
  } finally {
    loadingConfig.value = false
  }
}

async function save() {
  saving.value = true
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  try {
    const run = await forecastStore.createRun({
      forecast_name: form.forecast_name,
      name: form.forecast_name,
      forecast_type: form.forecast_type,
      historical_start_date: form.historical_start_date || null,
      historical_end_date: form.historical_end_date || null,
      forecast_start_date: form.forecast_start_date || null,
      forecast_end_date: form.forecast_end_date || null,
      period_start: form.forecast_start_date || null,
      period_end: form.forecast_end_date || null,
      principal_id: form.principal_id === '' ? null : Number(form.principal_id),
      revenue_category: form.revenue_category,
      simulation_count: form.simulation_count,
      target_revenue: form.target_revenue,
    })
    ui.notify('success', 'Forecast run dibuat. Simulasi sedang diproses.')
    router.push({ name: 'forecast-detail', params: { id: run.id } })
  } catch (e) {
    for (const f of [
      'forecast_name', 'forecast_type', 'historical_start_date', 'historical_end_date',
      'forecast_start_date', 'forecast_end_date', 'principal_id', 'revenue_category',
      'simulation_count', 'target_revenue',
    ]) {
      const msg = getFieldError(e, f)
      if (msg) fieldErrors[f] = msg
    }
    if (!Object.keys(fieldErrors).length) ui.notify('error', getApiErrorMessage(e))
  } finally {
    saving.value = false
  }
}

watch(() => form.forecast_start_date, (start) => {
  if (start && form.historical_end_date && form.historical_end_date >= start) {
    form.historical_end_date = dayBefore(start)
  }
})

onMounted(loadConfiguration)
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-4">
    <div class="card p-6">
      <h2 class="mb-1 text-base font-semibold text-slate-900">Buat Forecast</h2>
      <p class="mb-5 text-sm text-slate-500">
        Simulasi Monte Carlo menghitung prediksi revenue berdasarkan probabilitas historis per stage.
      </p>

      <div v-if="loadingConfig" class="h-64 animate-pulse rounded-lg bg-slate-100"></div>

      <form v-else class="space-y-5" @submit.prevent="save">
        <div>
          <label class="label">Nama Forecast *</label>
          <input v-model="form.forecast_name" class="input" placeholder="Forecast Revenue Q1 2027" />
          <p v-if="fieldErrors.forecast_name" class="mt-1 text-xs text-red-600">{{ fieldErrors.forecast_name }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label">Tipe Forecast *</label>
            <select v-model="form.forecast_type" class="input">
              <option v-for="t in FORECAST_TYPES" :key="t" :value="t">{{ t }}</option>
            </select>
            <p v-if="fieldErrors.forecast_type" class="mt-1 text-xs text-red-600">{{ fieldErrors.forecast_type }}</p>
          </div>
          <div>
            <label class="label">Revenue Category</label>
            <select v-model="form.revenue_category" class="input">
              <option v-for="c in configuration?.revenue_categories ?? ['ALL']" :key="c" :value="c">{{ c }}</option>
            </select>
            <p v-if="fieldErrors.revenue_category" class="mt-1 text-xs text-red-600">{{ fieldErrors.revenue_category }}</p>
          </div>
        </div>

        <div>
          <label class="label">Principal</label>
          <select v-model="form.principal_id" class="input">
            <option value="">Semua Principal</option>
            <option v-for="p in configuration?.principals ?? []" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <p v-if="fieldErrors.principal_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.principal_id }}</p>
        </div>

        <fieldset class="rounded-xl border border-slate-200 p-4">
          <legend class="px-1 text-sm font-medium text-slate-700">Periode Historis</legend>
          <p class="mb-3 text-xs text-slate-500">
            Digunakan untuk menghitung probabilitas WON/LOST per stage. Tidak boleh tumpang tindih dengan periode forecast.
          </p>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="label">Mulai *</label>
              <input v-model="form.historical_start_date" type="date" class="input" />
              <p v-if="fieldErrors.historical_start_date" class="mt-1 text-xs text-red-600">{{ fieldErrors.historical_start_date }}</p>
            </div>
            <div>
              <label class="label">Selesai *</label>
              <input v-model="form.historical_end_date" type="date" class="input" />
              <p v-if="fieldErrors.historical_end_date" class="mt-1 text-xs text-red-600">{{ fieldErrors.historical_end_date }}</p>
            </div>
          </div>
        </fieldset>

        <fieldset class="rounded-xl border border-slate-200 p-4">
          <legend class="px-1 text-sm font-medium text-slate-700">Periode Forecast</legend>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="label">Mulai *</label>
              <input v-model="form.forecast_start_date" type="date" class="input" />
              <p v-if="fieldErrors.forecast_start_date" class="mt-1 text-xs text-red-600">{{ fieldErrors.forecast_start_date }}</p>
            </div>
            <div>
              <label class="label">Selesai *</label>
              <input v-model="form.forecast_end_date" type="date" class="input" />
              <p v-if="fieldErrors.forecast_end_date" class="mt-1 text-xs text-red-600">{{ fieldErrors.forecast_end_date }}</p>
            </div>
          </div>
        </fieldset>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label">Jumlah Simulasi *</label>
            <input
              v-model.number="form.simulation_count"
              type="number"
              :min="simulationMin"
              :max="simulationMax"
              step="100"
              class="input"
            />
            <p class="mt-1 text-xs text-slate-500">{{ simCountLabel }} iterasi Monte Carlo</p>
            <p v-if="fieldErrors.simulation_count" class="mt-1 text-xs text-red-600">{{ fieldErrors.simulation_count }}</p>
          </div>
          <div>
            <label class="label">Target Revenue</label>
            <input v-model.number="form.target_revenue" type="number" min="0" class="input" placeholder="Rp..." />
            <p class="mt-1 text-xs text-slate-500">Untuk menghitung probabilitas pencapaian target</p>
            <p v-if="fieldErrors.target_revenue" class="mt-1 text-xs text-red-600">{{ fieldErrors.target_revenue }}</p>
          </div>
        </div>

        <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-xs text-blue-700">
          Simulasi dijalankan melalui queue. Halaman detail akan memperbarui status secara otomatis.
          {{ configuration?.opportunity_data.open_count ?? 0 }} opportunity OPEN tersedia.
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="router.back()">Batal</button>
          <button type="submit" class="btn-primary" :disabled="saving">
            {{ saving ? 'Menjalankan...' : 'Jalankan Simulasi' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
