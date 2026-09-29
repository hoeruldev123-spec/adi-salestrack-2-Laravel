<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { forecastsApi, type EvaluationResult } from '@/api/forecasts'
import { useFormat } from '@/composables/useFormat'
import { getApiErrorMessage } from '@/utils/form'
import BaseChart from '@/components/charts/BaseChart.vue'

const route = useRoute()
const router = useRouter()
const { formatRupiah } = useFormat()

const runId = Number(route.params.id)
const loading = ref(true)
const error = ref('')
const result = ref<EvaluationResult | null>(null)

const metrics = computed(() => {
  if (!result.value) return []
  return [
    { key: 'MAE', desc: 'Mean Absolute Error', value: result.value.mae },
    { key: 'MAPE', desc: 'Mean Absolute Percentage Error', value: result.value.mape, percent: true },
    { key: 'RMSE', desc: 'Root Mean Square Error', value: result.value.rmse },
  ]
})

const compareChart = computed(() => {
  const labels = Array.from(
    new Set([
      ...(result.value?.actual ?? []).map((a) => a.period),
      ...(result.value?.forecast ?? []).map((f) => f.period),
    ]),
  )
  const actualMap = Object.fromEntries((result.value?.actual ?? []).map((a) => [a.period, a.value]))
  const forecastMap = Object.fromEntries((result.value?.forecast ?? []).map((f) => [f.period, f.value]))
  return {
    type: 'line' as const,
    data: {
      labels,
      datasets: [
        {
          label: 'Actual',
          data: labels.map((l) => actualMap[l] ?? null),
          borderColor: '#10b981',
          backgroundColor: 'rgba(16, 185, 129, 0.1)',
          tension: 0.3,
          spanGaps: true,
        },
        {
          label: 'Forecast',
          data: labels.map((l) => forecastMap[l] ?? null),
          borderColor: '#6366f1',
          backgroundColor: 'rgba(99, 102, 241, 0.1)',
          borderDash: [6, 4],
          tension: 0.3,
          spanGaps: true,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: { ticks: { callback: (v: number | string) => `${Number(v) / 1_000_000}jt` } },
      },
    },
  }
})

const backtest = ref<unknown>(null)
const backtestError = ref('')
const backtestLoading = ref(false)

async function loadBacktest() {
  backtestLoading.value = true
  backtestError.value = ''
  try {
    backtest.value = await forecastsApi.backtest(runId)
  } catch (e) {
    backtestError.value = getApiErrorMessage(e)
  } finally {
    backtestLoading.value = false
  }
}

const backtestRows = computed(() => {
  const bt = backtest.value as { data?: Array<Record<string, unknown>>; periods?: Array<Record<string, unknown>> } | null
  const rows = bt?.data ?? bt?.periods ?? null
  if (Array.isArray(rows)) return rows
  return null
})

const backtestChart = computed(() => {
  const rows = backtestRows.value
  if (!rows || !rows.length) return null
  const labels = rows.map((r) => String(r.period ?? r.month ?? r.label ?? ''))
  const actual = rows.map((r) => Number(r.actual ?? r.value ?? 0))
  const forecast = rows.map((r) => Number(r.forecast ?? r.predicted ?? 0))
  if (!labels.some(Boolean)) return null
  return {
    type: 'line' as const,
    data: {
      labels,
      datasets: [
        { label: 'Actual', data: actual, borderColor: '#10b981', tension: 0.3 },
        { label: 'Backtest Forecast', data: forecast, borderColor: '#6366f1', borderDash: [6, 4], tension: 0.3 },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: { ticks: { callback: (v: number | string) => `${Number(v) / 1_000_000}jt` } },
      },
    },
  }
})

onMounted(async () => {
  loading.value = true
  error.value = ''
  try {
    result.value = await forecastsApi.evaluate(runId)
    await loadBacktest()
  } catch (e) {
    error.value = getApiErrorMessage(e)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-base font-semibold text-slate-900">Evaluasi Forecast Run #{{ runId }}</h2>
      <button class="btn-secondary btn-sm" @click="router.push({ name: 'forecast-detail', params: { id: runId } })">Kembali ke Detail</button>
    </div>

    <div v-if="loading" class="card h-64 animate-pulse"></div>

    <div v-else-if="error" class="card p-8 text-center">
      <p class="text-sm text-red-600">{{ error }}</p>
      <p class="mt-1 text-xs text-slate-500">Pastikan run sudah COMPLETED dan terdapat data actual revenue pada periode terkait.</p>
    </div>

    <template v-else-if="result">
      <div class="grid gap-4 sm:grid-cols-3">
        <div v-for="m in metrics" :key="m.key" class="card p-5 text-center">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ m.key }}</p>
          <p class="mt-1 text-lg font-bold text-slate-900">
            {{ m.percent ? `${Number(m.value).toFixed(2)}%` : formatRupiah(m.value) }}
          </p>
          <p class="mt-1 text-[11px] text-slate-500">{{ m.desc }}</p>
        </div>
      </div>

      <div class="card p-5">
        <h3 class="mb-4 text-sm font-semibold text-slate-800">Actual vs Forecast</h3>
        <BaseChart v-if="(result.actual.length || result.forecast.length)" :config="compareChart" :height="320" />
        <p v-else class="py-16 text-center text-sm text-slate-500">Belum ada data untuk perbandingan</p>
      </div>

      <div class="card p-5">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-800">Backtest (Perbandingan Historis)</h3>
          <button class="btn-secondary btn-sm" @click="loadBacktest">Muat Ulang</button>
        </div>
        <div v-if="backtestLoading" class="h-48 animate-pulse rounded-lg bg-slate-100"></div>
        <BaseChart v-else-if="backtestChart" :config="backtestChart" :height="300" />
        <template v-else-if="backtestRows && backtestRows.length">
          <div class="overflow-x-auto">
            <table class="table-base">
              <thead>
                <tr>
                  <th v-for="key in Object.keys(backtestRows[0])" :key="key" class="capitalize">{{ key.replace(/_/g, ' ') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in backtestRows" :key="i">
                  <td v-for="key in Object.keys(backtestRows[0])" :key="key">{{ row[key] }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
        <p v-else-if="backtestError" class="py-8 text-center text-sm text-slate-500">{{ backtestError }}</p>
        <p v-else class="py-8 text-center text-sm text-slate-500">Belum ada data backtest</p>
      </div>
    </template>
  </div>
</template>
