<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  forecastsApi,
  type ForecastBreakdownItem,
  type ForecastDistribution,
  type ForecastEvaluation,
  type ForecastRecommendation,
  type ForecastResultSummary,
  type ForecastRun,
} from '@/api/forecasts'
import { useForecastStore } from '@/stores/forecast'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { useFormat } from '@/composables/useFormat'
import { usePolling } from '@/composables/usePolling'
import { getApiErrorMessage } from '@/utils/form'
import BaseBadge from '@/components/common/BaseBadge.vue'
import BaseChart from '@/components/charts/BaseChart.vue'
import DistributionChart from '@/components/forecast/DistributionChart.vue'
import PercentileTable from '@/components/forecast/PercentileTable.vue'
import TargetProbability from '@/components/forecast/TargetProbability.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import {
  AlertTriangle,
  FlaskConical,
  Lightbulb,
  LineChart,
  Loader2,
  Plus,
  RefreshCw,
  Trash2,
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const auth = useAuthStore()
const forecastStore = useForecastStore()
const { formatRupiah, formatDate, formatNumber, formatPercent } = useFormat()

const runId = Number(route.params.id)
const result = ref<ForecastResultSummary | null>(null)
const distribution = ref<ForecastDistribution | null>(null)
const principals = ref<ForecastBreakdownItem[]>([])
const categories = ref<ForecastBreakdownItem[]>([])
const evaluation = ref<ForecastEvaluation | null>(null)
const recommendations = ref<ForecastRecommendation[]>([])
const resultLoading = ref(false)
const error = ref('')

const canManage = computed(() => auth.isAdmin || auth.isSalesManager)
const isDone = (run: ForecastRun) => run.status === 'COMPLETED' || run.status === 'FAILED'

const polling = usePolling(
  () => forecastsApi.get(runId),
  isDone,
  4000,
  10 * 60 * 1000,
)

const summaryCards = computed(() => {
  const r = result.value
  if (!r) return []
  return [
    { key: 'expected', label: 'Expected Revenue', value: r.expected_revenue, tone: 'text-primary-600' },
    { key: 'best', label: 'Best Case (P90)', value: r.best_case, tone: 'text-emerald-600' },
    { key: 'likely', label: 'Most Likely (P50)', value: r.most_likely, tone: 'text-slate-900' },
    { key: 'worst', label: 'Worst Case (P10)', value: r.worst_case, tone: 'text-red-600' },
  ]
})

const evaluationChart = computed(() => {
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

async function loadResult() {
  resultLoading.value = true
  error.value = ''
  try {
    const [summary, dist, principalRows, categoryRows, evaluationData] = await Promise.all([
      forecastsApi.resultSummary(runId),
      forecastsApi.distribution(runId),
      forecastsApi.principals(runId),
      forecastsApi.categories(runId),
      forecastsApi.monthlyEvaluation(runId),
    ])
    result.value = summary
    distribution.value = dist
    principals.value = principalRows
    categories.value = categoryRows
    evaluation.value = evaluationData

    try {
      const recs = await forecastsApi.recommendations(runId, { per_page: 5 })
      recommendations.value = recs.data
    } catch {
      recommendations.value = []
    }
  } catch (e) {
    error.value = getApiErrorMessage(e)
  } finally {
    resultLoading.value = false
  }
}

async function loadScenarios() {
  try {
    await forecastStore.fetchScenarios(runId)
  } catch {
    // scenarios may not be available yet
  }
}

async function removeRun() {
  const ok = await ui.askConfirmation({
    message: `Yakin ingin menghapus forecast "${polling.data?.forecast_name || polling.data?.name}"?`,
  })
  if (!ok) return
  try {
    await forecastsApi.remove(runId)
    ui.notify('success', 'Forecast dihapus')
    router.push('/forecasts')
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
  }
}

async function removeScenario(scenarioId: number, name: string) {
  const ok = await ui.askConfirmation({ message: `Yakin ingin menghapus scenario "${name}"?` })
  if (!ok) return
  try {
    await forecastStore.removeScenario(runId, scenarioId)
    ui.notify('success', 'Scenario dihapus')
    loadScenarios()
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
  }
}

const scenarioModal = ref(false)
const scenarioResult = ref<Awaited<ReturnType<typeof forecastsApi.scenarioResults>> | null>(null)

async function viewScenario(scenarioId: number) {
  try {
    scenarioResult.value = await forecastsApi.scenarioResults(runId, scenarioId)
    scenarioModal.value = true
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
  }
}

function openRecommendations() {
  router.push({ name: 'recommendations', query: { forecast_run_id: String(runId) } })
}

watch(
  () => polling.data?.status,
  (status, oldStatus) => {
    forecastStore.currentRun = polling.data
    if (status === 'COMPLETED' && oldStatus !== 'COMPLETED') {
      loadResult()
      loadScenarios()
    }
  },
)

onMounted(async () => {
  try {
    const run = await forecastsApi.get(runId)
    polling.data = run
    forecastStore.currentRun = run
    if (run.status === 'COMPLETED') {
      await loadResult()
    } else if (!isDone(run)) {
      polling.start(false)
    }
    await loadScenarios()
  } catch (e) {
    error.value = getApiErrorMessage(e)
  }
})
</script>

<template>
  <div v-if="polling.data" class="space-y-6">
    <div class="card p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-lg font-semibold text-slate-900">
              {{ polling.data.forecast_name || polling.data.name }}
            </h2>
            <BaseBadge :value="polling.data.forecast_type" />
            <BaseBadge :value="polling.data.status" />
          </div>
          <p class="mt-1 text-sm text-slate-500">
            Forecast: {{ formatDate(polling.data.forecast_start_date || polling.data.period_start) }} →
            {{ formatDate(polling.data.forecast_end_date || polling.data.period_end) }}
            · Historis: {{ formatDate(polling.data.historical_start_date) }} →
            {{ formatDate(polling.data.historical_end_date) }}
          </p>
          <p class="mt-1 text-xs text-slate-500">
            {{ formatNumber(polling.data.simulation_count) }} simulasi
            · {{ polling.data.revenue_category || 'ALL' }}
            · {{ polling.data.principal?.name || 'Semua Principal' }}
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button v-if="canManage" class="btn-secondary btn-sm" @click="router.push({ name: 'forecast-evaluation', params: { id: runId } })">
            <LineChart class="h-3.5 w-3.5" /> Evaluasi
          </button>
          <button v-if="canManage" class="btn-secondary btn-sm" @click="openRecommendations">
            <Lightbulb class="h-3.5 w-3.5" /> Rekomendasi
          </button>
          <button v-if="canManage" class="btn-danger btn-sm" @click="removeRun">
            <Trash2 class="h-3.5 w-3.5" /> Hapus
          </button>
        </div>
      </div>

      <div
        v-if="polling.data.status === 'PENDING' || polling.data.status === 'PROCESSING'"
        class="mt-6 rounded-xl border border-blue-200 bg-blue-50 p-6 text-center"
      >
        <Loader2 class="mx-auto h-10 w-10 animate-spin text-blue-500" />
        <h3 class="mt-3 text-sm font-semibold text-blue-800">Simulasi Monte Carlo sedang berjalan</h3>
        <p class="mt-1 text-xs text-blue-600">
          {{ formatNumber(polling.data.simulation_count) }} iterasi diproses melalui queue. Halaman ini
          diperbarui otomatis.
        </p>
      </div>

      <div
        v-else-if="polling.data.status === 'FAILED'"
        class="mt-6 rounded-xl border border-red-200 bg-red-50 p-6 text-center"
      >
        <h3 class="text-sm font-semibold text-red-800">Simulasi gagal</h3>
        <p class="mt-1 text-xs text-red-600">
          {{ polling.data.warnings?.[0] || 'Terjadi kesalahan saat proses simulasi' }}
        </p>
      </div>

      <div v-else class="mt-4 flex flex-wrap items-center gap-4 text-sm text-slate-500">
        <span>Status: <span class="font-medium text-emerald-600">Selesai</span></span>
        <span v-if="polling.data.completed_at">Selesai pada: {{ formatDate(polling.data.completed_at, true) }}</span>
        <button class="btn-ghost btn-sm" @click="loadResult">
          <RefreshCw class="h-3.5 w-3.5" /> Muat ulang hasil
        </button>
      </div>
    </div>

    <div
      v-if="(result?.warnings?.length || polling.data.warnings?.length)"
      class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
    >
      <p class="flex items-center gap-2 font-medium"><AlertTriangle class="h-4 w-4" /> Catatan data historis</p>
      <ul class="mt-2 list-disc space-y-1 pl-6 text-xs">
        <li v-for="warning in (result?.warnings?.length ? result.warnings : (polling.data.warnings ?? []))" :key="warning">
          {{ warning }}
        </li>
      </ul>
    </div>

    <template v-if="polling.data.status === 'COMPLETED'">
      <div v-if="resultLoading" class="card h-64 animate-pulse"></div>
      <div v-else-if="error" class="card p-6 text-sm text-red-600">{{ error }}</div>

      <template v-else-if="result">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div v-for="card in summaryCards" :key="card.key" class="card p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ card.label }}</p>
            <p class="mt-2 text-xl font-bold" :class="card.tone">{{ formatRupiah(card.value) }}</p>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
          <div class="card p-5">
            <h3 class="mb-4 text-sm font-semibold text-slate-800">Ringkasan Simulasi</h3>
            <dl class="space-y-2 text-sm">
              <div class="flex justify-between gap-3">
                <dt class="text-slate-500">Opportunity</dt>
                <dd class="font-medium text-slate-800">{{ formatNumber(result.total_opportunities) }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-slate-500">Total Nilai</dt>
                <dd class="font-medium text-slate-800">{{ formatRupiah(result.total_opportunity_value) }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-slate-500">Expected Opportunity</dt>
                <dd class="font-medium text-slate-800">{{ formatRupiah(result.expected_opportunity_value) }}</dd>
              </div>
              <div class="flex justify-between gap-3">
                <dt class="text-slate-500">MAPE</dt>
                <dd class="font-medium text-slate-800">
                  {{ result.mape != null ? formatPercent(result.mape, 2) : '-' }}
                </dd>
              </div>
            </dl>
            <div class="mt-5 border-t border-slate-100 pt-4">
              <TargetProbability
                :probability="result.target_probability"
                :target="polling.data.target_revenue"
                :revenue="result.mean"
              />
            </div>
            <div class="mt-5 border-t border-slate-100 pt-4">
              <PercentileTable :result="result" />
            </div>
          </div>
          <div class="card p-5 lg:col-span-2">
            <h3 class="mb-4 text-sm font-semibold text-slate-800">Distribusi Revenue (Monte Carlo)</h3>
            <DistributionChart v-if="distribution" :distribution="distribution" />
            <div v-else class="py-16 text-center text-sm text-slate-500">Distribusi belum tersedia</div>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="card overflow-hidden">
            <div class="card-header">
              <h3 class="text-sm font-semibold text-slate-800">Revenue per Principal</h3>
            </div>
            <div class="overflow-x-auto">
              <table class="table-base">
                <thead>
                  <tr>
                    <th>Principal</th>
                    <th class="text-right">Opportunity</th>
                    <th class="text-right">Nilai</th>
                    <th class="text-right">Expected</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in principals" :key="row.principal_id ?? 'unassigned'">
                    <td class="font-medium text-slate-800">{{ row.principal }}</td>
                    <td class="text-right">{{ formatNumber(row.opportunity_count) }}</td>
                    <td class="text-right">{{ formatRupiah(row.estimated_value) }}</td>
                    <td class="text-right font-medium">{{ formatRupiah(row.expected_revenue) }}</td>
                  </tr>
                  <tr v-if="!principals.length">
                    <td colspan="4" class="py-8 text-center text-sm text-slate-500">Belum ada data</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card overflow-hidden">
            <div class="card-header">
              <h3 class="text-sm font-semibold text-slate-800">Revenue per Category</h3>
            </div>
            <div class="overflow-x-auto">
              <table class="table-base">
                <thead>
                  <tr>
                    <th>Kategori</th>
                    <th class="text-right">Opportunity</th>
                    <th class="text-right">Nilai</th>
                    <th class="text-right">Expected</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in categories" :key="row.category">
                    <td class="font-medium text-slate-800">{{ row.category }}</td>
                    <td class="text-right">{{ formatNumber(row.opportunity_count) }}</td>
                    <td class="text-right">{{ formatRupiah(row.estimated_value) }}</td>
                    <td class="text-right font-medium">{{ formatRupiah(row.expected_revenue) }}</td>
                  </tr>
                  <tr v-if="!categories.length">
                    <td colspan="4" class="py-8 text-center text-sm text-slate-500">Belum ada data</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="card p-5">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
              <h3 class="text-sm font-semibold text-slate-800">Forecast vs Actual</h3>
              <p class="text-xs text-slate-500">{{ evaluation?.interpretation || 'Belum ada data aktual' }}</p>
            </div>
            <div class="flex items-center gap-3 text-sm">
              <span class="text-slate-500">MAPE:</span>
              <span class="font-semibold text-slate-900">
                {{ evaluation?.mape != null ? formatPercent(evaluation.mape, 2) : '-' }}
              </span>
            </div>
          </div>
          <BaseChart v-if="evaluationChart" :config="evaluationChart" :height="300" />
          <p v-else class="py-12 text-center text-sm text-slate-500">Belum ada data untuk perbandingan</p>
        </div>

        <div class="card overflow-hidden">
          <div class="card-header">
            <h3 class="text-sm font-semibold text-slate-800">Detail Opportunity</h3>
            <span class="text-xs text-slate-500">{{ result.total_opportunities }} opportunity</span>
          </div>
          <div class="overflow-x-auto">
            <table class="table-base">
              <thead>
                <tr>
                  <th>Opportunity</th>
                  <th>Principal</th>
                  <th>Stage</th>
                  <th class="text-right">Nilai</th>
                  <th class="text-right">Probabilitas</th>
                  <th>Closing Date</th>
                  <th>Aktivitas Terakhir</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="opp in result.opportunities" :key="opp.id">
                  <td>
                    <p class="font-medium text-slate-800">{{ opp.title }}</p>
                    <p class="text-xs text-slate-500">{{ opp.client_name || '-' }}</p>
                  </td>
                  <td>{{ opp.principal_name || '-' }}</td>
                  <td><BaseBadge :value="opp.stage" /></td>
                  <td class="text-right font-medium">{{ formatRupiah(opp.value) }}</td>
                  <td class="text-right">
                    <span class="font-medium">{{ formatPercent(opp.probability_percent, 1) }}</span>
                    <span class="block text-[11px] text-slate-500">{{ opp.probability_source }}</span>
                  </td>
                  <td>{{ formatDate(opp.closing_date) }}</td>
                  <td>{{ formatDate(opp.last_activity_at) }}</td>
                </tr>
                <tr v-if="!result.opportunities.length">
                  <td colspan="7" class="py-10 text-center text-sm text-slate-500">
                    Tidak ada opportunity yang sesuai filter forecast.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
              <Lightbulb class="h-4 w-4 text-amber-500" /> Rekomendasi Teratas
            </h3>
            <button class="btn-secondary btn-sm" @click="openRecommendations">Lihat Semua</button>
          </div>
          <EmptyState
            v-if="recommendations.length === 0"
            title="Belum ada rekomendasi"
            subtitle="Rekomendasi dihasilkan setelah simulasi selesai."
          >
            <template #icon><Lightbulb class="h-6 w-6" /></template>
          </EmptyState>
          <ul v-else class="divide-y divide-slate-100">
            <li v-for="rec in recommendations" :key="rec.id" class="flex flex-wrap items-start justify-between gap-3 px-5 py-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <BaseBadge :value="rec.priority" />
                  <p class="text-sm font-medium text-slate-800">{{ rec.recommendation_title }}</p>
                </div>
                <p class="mt-1 text-xs text-slate-500">{{ rec.action_suggestion }}</p>
              </div>
              <div class="text-right text-xs text-slate-500">
                <p class="font-medium text-slate-800">{{ formatRupiah(rec.estimated_value) }}</p>
                <p>{{ rec.probability != null ? formatPercent(rec.probability, 1) : '-' }}</p>
              </div>
            </li>
          </ul>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
              <FlaskConical class="h-4 w-4 text-primary-500" /> Scenario Analysis
            </h3>
            <button v-if="canManage" class="btn-primary btn-sm" @click="router.push({ name: 'scenario-new', params: { id: runId } })">
              <Plus class="h-3.5 w-3.5" /> Buat Scenario
            </button>
          </div>
          <EmptyState
            v-if="forecastStore.scenarios.length === 0"
            title="Belum ada scenario"
            subtitle="Buat scenario untuk analisis what-if."
          >
            <template #icon><FlaskConical class="h-6 w-6" /></template>
          </EmptyState>
          <ul v-else class="divide-y divide-slate-100">
            <li v-for="s in forecastStore.scenarios" :key="s.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
              <div>
                <p class="text-sm font-medium text-slate-800">{{ s.name }}</p>
                <p class="text-xs text-slate-500">
                  Win rate: {{ s.win_rate_adjustment != null ? `${s.win_rate_adjustment}%` : '-' }} ·
                  Revenue: {{ s.revenue_adjustment != null ? `${s.revenue_adjustment}%` : '-' }}
                </p>
              </div>
              <div class="flex items-center gap-2">
                <button v-if="canManage" class="btn-secondary btn-sm" @click="viewScenario(s.id)">
                  <RefreshCw class="h-3.5 w-3.5" /> Jalankan
                </button>
                <button v-if="canManage" class="btn-danger btn-sm" @click="removeScenario(s.id, s.name)">
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              </div>
            </li>
          </ul>
        </div>
      </template>
    </template>
  </div>

  <div v-else class="card p-10 text-center text-sm text-slate-500">Memuat forecast...</div>

  <BaseModal :open="scenarioModal" title="Hasil Scenario" width="max-w-xl" @close="scenarioModal = false">
    <div v-if="scenarioResult" class="space-y-4">
      <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3">
        <div>
          <p class="text-sm font-medium text-slate-800">{{ scenarioResult.scenario_name }}</p>
          <p class="text-xs text-slate-500">Perbandingan current vs scenario</p>
        </div>
        <BaseBadge value="SCENARIO" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div class="rounded-lg border border-slate-200 p-4">
          <p class="text-xs text-slate-500">Current (P50)</p>
          <p class="mt-1 text-lg font-bold text-slate-900">
            {{ formatRupiah(scenarioResult.current?.P50 ?? scenarioResult.current?.p50) }}
          </p>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
          <p class="text-xs text-emerald-600">Scenario (P50)</p>
          <p class="mt-1 text-lg font-bold text-emerald-700">
            {{ formatRupiah(scenarioResult.scenario?.P50 ?? scenarioResult.scenario?.p50) }}
          </p>
        </div>
      </div>
      <div class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3">
        <p class="text-sm text-slate-600">Potensi Peningkatan</p>
        <p class="text-base font-bold text-emerald-600">
          {{ scenarioResult.potential_increase != null ? `${scenarioResult.potential_increase.toFixed(2)}%` : '-' }}
        </p>
      </div>
      <div class="flex justify-end">
        <button class="btn-secondary" @click="scenarioModal = false">Tutup</button>
      </div>
    </div>
  </BaseModal>
</template>
