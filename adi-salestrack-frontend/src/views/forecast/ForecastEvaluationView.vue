<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { forecastsApi, type ForecastEvaluation } from '@/api/forecasts'
import { useFormat } from '@/composables/useFormat'
import { getApiErrorMessage } from '@/utils/form'
import BaseBadge from '@/components/common/BaseBadge.vue'
import BaseChart from '@/components/charts/BaseChart.vue'

const route = useRoute()
const router = useRouter()
const { formatRupiah, formatPercent } = useFormat()

const runId = Number(route.params.id)
const loading = ref(true)
const error = ref('')
const evaluation = ref<ForecastEvaluation | null>(null)

const chartConfig = computed(() => {
  const periods = evaluation.value?.periods ?? []
  if (!periods.length) return null
  return {
    type: 'line' as const,
    data: {
      labels: periods.map((p) => p.label),
      datasets: [
        {
          label: 'Forecast',
          data: periods.map((p) => p.forecast),
          borderColor: '#6366f1',
          backgroundColor: 'rgba(99, 102, 241, 0.1)',
          tension: 0.3,
        },
        {
          label: 'Actual',
          data: periods.map((p) => p.actual),
          borderColor: '#10b981',
          backgroundColor: 'rgba(16, 185, 129, 0.1)',
          tension: 0.3,
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

onMounted(async () => {
  loading.value = true
  error.value = ''
  try {
    evaluation.value = await forecastsApi.monthlyEvaluation(runId)
  } catch (e) {
    error.value = getApiErrorMessage(e)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div>
        <h2 class="text-base font-semibold text-slate-900">Evaluasi Forecast</h2>
        <p class="text-xs text-slate-500">
          Perbandingan forecast vs pendapatan aktual per bulan.
        </p>
      </div>
      <button class="btn-secondary btn-sm" @click="router.push({ name: 'forecast-detail', params: { id: runId } })">
        Kembali ke Hasil Prediksi
      </button>
    </div>

    <div v-if="loading" class="card h-64 animate-pulse"></div>

    <div v-else-if="error" class="card p-8 text-center">
      <p class="text-sm text-red-600">{{ error }}</p>
    </div>

    <template v-else-if="evaluation">
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</p>
          <div class="mt-2"><BaseBadge :value="evaluation.status" /></div>
          <p class="mt-2 text-xs text-slate-500">{{ evaluation.interpretation }}</p>
        </div>
        <div class="card p-5">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">MAPE</p>
          <p class="mt-2 text-xl font-bold text-slate-900">
            {{ evaluation.mape != null ? formatPercent(evaluation.mape, 2) : '-' }}
          </p>
          <p class="mt-1 text-xs text-slate-500">{{ evaluation.evaluated_periods }} periode dievaluasi</p>
        </div>
        <div class="card p-5">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total Forecast</p>
          <p class="mt-2 text-xl font-bold text-primary-600">{{ formatRupiah(evaluation.total_forecast) }}</p>
        </div>
        <div class="card p-5">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total Actual</p>
          <p class="mt-2 text-xl font-bold text-emerald-600">{{ formatRupiah(evaluation.total_actual) }}</p>
        </div>
      </div>

      <div class="card p-5">
        <h3 class="mb-4 text-sm font-semibold text-slate-800">Forecast vs Actual per Bulan</h3>
        <BaseChart v-if="chartConfig" :config="chartConfig" :height="320" />
        <p v-else class="py-16 text-center text-sm text-slate-500">Belum ada data</p>
      </div>

      <div class="card overflow-hidden">
        <div class="card-header">
          <h3 class="text-sm font-semibold text-slate-800">Rincian Periode</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="table-base">
            <thead>
              <tr>
                <th>Periode</th>
                <th class="text-right">Forecast</th>
                <th class="text-right">Actual</th>
                <th class="text-right">Error (%)</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="period in evaluation.periods" :key="period.period">
                <td class="font-medium text-slate-800">{{ period.label }}</td>
                <td class="text-right">{{ formatRupiah(period.forecast) }}</td>
                <td class="text-right">{{ formatRupiah(period.actual) }}</td>
                <td class="text-right">{{ period.error_percentage != null ? formatPercent(period.error_percentage, 2) : '-' }}</td>
                <td><BaseBadge :value="period.status" /></td>
              </tr>
              <tr v-if="!evaluation.periods.length">
                <td colspan="5" class="py-8 text-center text-sm text-slate-500">Belum ada periode</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
