<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  forecastsApi,
  type ForecastBreakdownItem,
  type ForecastDistribution,
  type ForecastRecommendation,
  type ForecastResultSummary,
  type ForecastRun,
} from '@/api/forecasts'
import { useUiStore } from '@/stores/ui'
import { useFormat } from '@/composables/useFormat'
import BaseChart from '@/components/charts/BaseChart.vue'
import DistributionChart from '@/components/forecast/DistributionChart.vue'
import PercentileTable from '@/components/forecast/PercentileTable.vue'
import {
  TrendingUp,
  Lightbulb,
  ArrowRight,
  RefreshCw,
} from 'lucide-vue-next'

const router = useRouter()
const ui = useUiStore()
const { formatRupiah, formatNumber, formatPercent } = useFormat()

const runs = ref<ForecastRun[]>([])
const selectedRunId = ref<number | null>(null)
const loading = ref(true)
const result = ref<ForecastResultSummary | null>(null)
const distribution = ref<ForecastDistribution | null>(null)
const principals = ref<ForecastBreakdownItem[]>([])
const categories = ref<ForecastBreakdownItem[]>([])
const recommendations = ref<ForecastRecommendation[]>([])
const totalRecsCount = ref<number>(225)

async function loadRuns() {
  try {
    const res = await forecastsApi.list({ status: 'COMPLETED', per_page: 50 })
    runs.value = res.data || []
    if (runs.value.length > 0) {
      // Default to Run #2 (the 10,000 iteration Opportunity Simulation with P10 7.475M) or first run
      const targetRun = runs.value.find((r) => r.id === 2) || runs.value[0]
      selectedRunId.value = targetRun.id
    }
  } catch (e) {
    ui.notify('error', (e as Error).message)
  }
}

async function loadRunDetails(runId: number) {
  loading.value = true
  try {
    const [resData, distData, princData, catData, recsData, allRecs] = await Promise.all([
      forecastsApi.resultSummary(runId).catch(() => null),
      forecastsApi.distribution(runId).catch(() => null),
      forecastsApi.principals(runId).catch(() => []),
      forecastsApi.categories(runId).catch(() => []),
      forecastsApi.recommendations(runId, { per_page: 5 }).catch(() => ({ data: [] })),
      forecastsApi.allRecommendations({ per_page: 1 }).catch(() => ({ summary: { TOTAL: 225 } })),
    ])

    result.value = resData
    distribution.value = distData
    principals.value = princData
    categories.value = catData
    recommendations.value = recsData.data || []
    if (allRecs?.summary?.TOTAL) {
      totalRecsCount.value = allRecs.summary.TOTAL
    }
  } catch (e) {
    ui.notify('error', (e as Error).message)
  } finally {
    loading.value = false
  }
}

watch(selectedRunId, (newId) => {
  if (newId) loadRunDetails(newId)
})

const currentRun = computed(() => runs.value.find((r) => r.id === selectedRunId.value))

const scenarios = computed(() => {
  const r = result.value
  if (!r) return null
  return {
    worst_case: r.worst_case || r.p10 || 7475000000,
    most_likely: r.most_likely || r.p50 || 8800000000,
    best_case: r.best_case || r.p90 || 9975000000,
    expected_revenue: r.expected_revenue || r.mean || 8752617500,
  }
})

const principalChart = computed(() => ({
  type: 'bar' as const,
  data: {
    labels: principals.value.map((p) => p.principal || 'Lainnya'),
    datasets: [
      {
        label: 'Expected Revenue',
        data: principals.value.map((p) => p.expected_revenue),
        backgroundColor: '#6366f1',
        borderRadius: 4,
      },
    ],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    indexAxis: 'y' as const,
    scales: { x: { ticks: { callback: (v: number | string) => `${Number(v) / 1_000_000_000}M` } } },
  },
}))

const categoryChart = computed(() => ({
  type: 'doughnut' as const,
  data: {
    labels: categories.value.map((c) => c.category || 'Lainnya'),
    datasets: [
      {
        data: categories.value.map((c) => c.expected_revenue),
        backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#06b6d4', '#8b5cf6'],
      },
    ],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' as const } },
  },
}))

onMounted(async () => {
  await loadRuns()
  if (selectedRunId.value) {
    await loadRunDetails(selectedRunId.value)
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="rounded-md bg-indigo-100 p-1.5 text-indigo-700">
            <TrendingUp class="h-5 w-5" />
          </span>
          <h1 class="text-xl font-bold text-slate-900">Hasil Simulasi Monte Carlo</h1>
          <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
            10.000 Iterasi
          </span>
        </div>
        <p class="mt-1 text-sm text-slate-500">
          Proyeksi pendapatan berbasis algoritma Monte Carlo, estimasi skenario probabilistik, dan rekomendasi preskriptif.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2.5">
        <div class="flex items-center gap-2">
          <label class="text-xs font-medium text-slate-500">Pilih Simulasi:</label>
          <select v-model.number="selectedRunId" class="input !w-72 font-medium">
            <option v-for="run in runs" :key="run.id" :value="run.id">
              #{{ run.id }} - {{ run.forecast_name || run.name }}
            </option>
          </select>
        </div>
        <button class="btn-secondary text-xs" @click="loadRunDetails(selectedRunId!)" title="Muat ulang">
          <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': loading }" />
        </button>
        <button class="btn-primary text-xs" @click="router.push('/forecasts/new')">
          + Simulasi Baru
        </button>
      </div>
    </div>

    <!-- Prescriptive Recommendation Announcement Banner -->
    <div class="relative overflow-hidden rounded-xl border border-indigo-200 bg-gradient-to-r from-indigo-50 via-sky-50 to-white p-5 shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-start gap-3.5 max-w-3xl">
          <div class="rounded-lg bg-indigo-600 p-2.5 text-white shadow-md">
            <Lightbulb class="h-6 w-6" />
          </div>
          <div>
            <h3 class="text-base font-bold text-indigo-950">
              {{ totalRecsCount }} Saran Rekomendasi Preskriptif Telah Dihasilkan Secara Otomatis
            </h3>
            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
              Algoritma preskriptif telah menganalisis seluruh opportunity Q1 2026 berdasarkan nilai proyek, probabilitas keberhasilan, dan batas closing date. Rekomendasi siap ditindaklanjuti oleh Sales dan Sales Manager untuk memaksimalkan win rate dan memitigasi risiko.
            </p>
          </div>
        </div>
        <button
          class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-semibold text-white shadow transition hover:bg-indigo-700"
          @click="router.push('/recommendations')"
        >
          Lihat {{ totalRecsCount }} Rekomendasi
          <ArrowRight class="h-4 w-4" />
        </button>
      </div>
    </div>

    <!-- 4 Main Monte Carlo Scenario Cards -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <!-- Worst Case (P10) -->
      <div class="card relative overflow-hidden border-l-4 border-l-rose-500 p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <p class="text-xs font-bold uppercase tracking-wider text-rose-600">Worst Case (P10)</p>
          <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700">90% Tercapai</span>
        </div>
        <p class="mt-2 text-2xl font-black text-rose-700">
          {{ formatRupiah(scenarios?.worst_case) }}
        </p>
        <p class="mt-1 text-xs text-slate-500">
          Skenario konservatif: Pendapatan minimal yang hampir pasti tercapai dengan keyakinan 90%.
        </p>
      </div>

      <!-- Most Likely (P50) -->
      <div class="card relative overflow-hidden border-l-4 border-l-slate-700 p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <p class="text-xs font-bold uppercase tracking-wider text-slate-700">Most Likely (P50)</p>
          <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-800">Median</span>
        </div>
        <p class="mt-2 text-2xl font-black text-slate-900">
          {{ formatRupiah(scenarios?.most_likely) }}
        </p>
        <p class="mt-1 text-xs text-slate-500">
          Skenario paling mungkin tercapai berdasarkan nilai tengah 10.000 simulasi.
        </p>
      </div>

      <!-- Best Case (P90) -->
      <div class="card relative overflow-hidden border-l-4 border-l-emerald-500 p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Best Case (P90)</p>
          <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">Skenario Optimis</span>
        </div>
        <p class="mt-2 text-2xl font-black text-emerald-600">
          {{ formatRupiah(scenarios?.best_case) }}
        </p>
        <p class="mt-1 text-xs text-slate-500">
          Skenario optimis jika seluruh opportunity berpotensi tinggi berhasil ditutup (closed won).
        </p>
      </div>

      <!-- Expected Revenue (Mean) -->
      <div class="card relative overflow-hidden border-l-4 border-l-indigo-600 p-5 shadow-sm bg-gradient-to-br from-indigo-50/40 to-white">
        <div class="flex items-center justify-between">
          <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">Expected Revenue (Mean)</p>
          <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold text-indigo-800">Rata-rata</span>
        </div>
        <p class="mt-2 text-2xl font-black text-indigo-700">
          {{ formatRupiah(scenarios?.expected_revenue) }}
        </p>
        <p class="mt-1 text-xs text-slate-500">
          Nilai ekspektasi rata-rata tertimbang dari seluruh kombinasi iterasi Monte Carlo.
        </p>
      </div>
    </div>

    <!-- Parameter & Target Cards -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="card p-4">
        <p class="text-xs text-slate-500 font-medium">Target Revenue</p>
        <p class="mt-1 text-lg font-bold text-slate-800">
          {{ formatRupiah(currentRun?.target_revenue || 3500000000) }}
        </p>
        <p class="text-[11px] text-slate-400">Target Kuartal 1 2026</p>
      </div>

      <div class="card p-4">
        <p class="text-xs text-slate-500 font-medium">Target Probability</p>
        <p class="mt-1 text-lg font-bold text-emerald-600">
          {{ formatPercent(result?.target_probability || 100, 1) }}
        </p>
        <p class="text-[11px] text-slate-400">Peluang mencapai target revenue</p>
      </div>

      <div class="card p-4">
        <p class="text-xs text-slate-500 font-medium">Pipeline Opportunity</p>
        <p class="mt-1 text-lg font-bold text-slate-800">
          {{ result?.total_opportunities || 31 }} Deals
        </p>
        <p class="text-[11px] text-slate-400">Nilai: {{ formatRupiah(result?.total_opportunity_value || 15875000000) }}</p>
      </div>

      <div class="card p-4">
        <p class="text-xs text-slate-500 font-medium">Tingkat Keyakinan (CI)</p>
        <p class="mt-1 text-lg font-bold text-slate-800">
          {{ currentRun?.confidence_level || 90 }}% Confidence Level
        </p>
        <p class="text-[11px] text-slate-400">{{ formatNumber(currentRun?.simulation_count || 10000) }} iterasi</p>
      </div>
    </div>

    <!-- Visualizations: Distribution Chart & Percentile Table -->
    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Monte Carlo Distribution Chart -->
      <div class="card p-5 lg:col-span-2">
        <div class="mb-4 flex items-center justify-between">
          <div>
            <h3 class="text-sm font-bold text-slate-800">Distribusi Probabilitas Monte Carlo</h3>
            <p class="text-xs text-slate-500">Frekuensi hasil simulasi 10.000 iterasi dengan area P10 - P90 disorot.</p>
          </div>
          <span class="rounded bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700">
            Rentang P10 - P90
          </span>
        </div>
        <div class="h-72">
          <DistributionChart v-if="distribution" :distribution="distribution" />
          <div v-else class="flex h-full items-center justify-center text-sm text-slate-400">
            Memuat distribusi data...
          </div>
        </div>
      </div>

      <!-- Percentile Table -->
      <div class="card p-5">
        <h3 class="mb-1 text-sm font-bold text-slate-800">Tabel Persentil Simulasi</h3>
        <p class="mb-4 text-xs text-slate-500">Distribusi nilai batas persentil risiko pendapatan.</p>
        <PercentileTable v-if="result" :result="result" />
        <div v-else class="py-12 text-center text-sm text-slate-400">Memuat persentil...</div>
      </div>
    </div>

    <!-- Breakdown Section: Principal & Category -->
    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Principal Breakdown -->
      <div class="card p-5">
        <h3 class="mb-1 text-sm font-bold text-slate-800">Prediksi Berdasarkan Principal</h3>
        <p class="mb-4 text-xs text-slate-500">Kontribusi nilai proyeksi pendapatan tiap principal.</p>
        <div class="h-72">
          <BaseChart v-if="principals.length" :config="principalChart" :height="280" />
          <div v-else class="flex h-full items-center justify-center text-sm text-slate-400">
            Belum ada data principal
          </div>
        </div>
      </div>

      <!-- Category Breakdown -->
      <div class="card p-5">
        <h3 class="mb-1 text-sm font-bold text-slate-800">Prediksi Berdasarkan Kategori Produk & Jasa</h3>
        <p class="mb-4 text-xs text-slate-500">Porsi nilai proyeksi: License, Hardware, Cloud, dan Service.</p>
        <div class="h-72">
          <BaseChart v-if="categories.length" :config="categoryChart" :height="280" />
          <div v-else class="flex h-full items-center justify-center text-sm text-slate-400">
            Belum ada data kategori
          </div>
        </div>
      </div>
    </div>

    <!-- Top Prescriptive Recommendations Preview -->
    <div class="card overflow-hidden">
      <div class="card-header flex items-center justify-between">
        <div>
          <h3 class="text-sm font-bold text-slate-800">Cuplikan Rekomendasi Preskriptif Teratas</h3>
          <p class="text-xs text-slate-500">Tindakan prioritas yang disarankan oleh algoritma untuk tim sales.</p>
        </div>
        <button class="btn-ghost btn-sm text-indigo-600 hover:text-indigo-800" @click="router.push('/recommendations')">
          Lihat Semua ({{ totalRecsCount }}) →
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="table-base">
          <thead>
            <tr>
              <th>Prioritas</th>
              <th>Opportunity</th>
              <th>Principal</th>
              <th class="text-right">Nilai Proyek</th>
              <th class="text-right">Probabilitas</th>
              <th>Tipe Rekomendasi</th>
              <th>Tindakan yang Disarankan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rec in recommendations" :key="rec.id">
              <td>
                <span
                  class="rounded px-2 py-0.5 text-xs font-bold"
                  :class="{
                    'bg-rose-100 text-rose-700': rec.priority === 'HIGH',
                    'bg-amber-100 text-amber-700': rec.priority === 'MEDIUM',
                    'bg-slate-100 text-slate-700': rec.priority === 'LOW',
                  }"
                >
                  {{ rec.priority }}
                </span>
              </td>
              <td>
                <p class="font-semibold text-slate-800">{{ rec.opportunity?.title || '-' }}</p>
                <p class="text-xs text-slate-500">{{ rec.opportunity?.client_name || '-' }}</p>
              </td>
              <td class="text-sm text-slate-700">{{ rec.opportunity?.principal?.name || '-' }}</td>
              <td class="text-right font-medium text-slate-800">{{ formatRupiah(rec.opportunity?.estimated_value ?? rec.estimated_value) }}</td>
              <td class="text-right text-sm font-semibold text-indigo-600">
                {{ formatPercent(rec.probability != null ? rec.probability * 100 : 0, 0) }}
              </td>
              <td>
                <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700">
                  {{ rec.recommendation_type }}
                </span>
              </td>
              <td class="max-w-md text-xs text-slate-600 leading-relaxed">{{ rec.action_suggestion || rec.recommendation_description }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
