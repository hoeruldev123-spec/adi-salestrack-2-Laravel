<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { forecastsApi, type ForecastResult } from '@/api/forecasts'
import { useForecastStore } from '@/stores/forecast'
import { useUiStore } from '@/stores/ui'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'
import { usePolling } from '@/composables/usePolling'
import { getApiErrorMessage } from '@/utils/form'
import BaseBadge from '@/components/common/BaseBadge.vue'
import DistributionChart from '@/components/forecast/DistributionChart.vue'
import PercentileTable from '@/components/forecast/PercentileTable.vue'
import TargetProbability from '@/components/forecast/TargetProbability.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import {
  Loader2,
  Trash2,
  Plus,
  FlaskConical,
  LineChart,
  RefreshCw,
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const auth = useAuthStore()
const forecastStore = useForecastStore()
const { formatRupiah, formatDate, formatNumber } = useFormat()

const runId = Number(route.params.id)
const result = ref<ForecastResult | null>(null)
const resultLoading = ref(false)
const error = ref('')

const canManage = computed(() => auth.isAdmin || auth.isSalesManager)

const isDone = (run: { status: string }) => run.status === 'COMPLETED' || run.status === 'FAILED'

const polling = usePolling(
  () => forecastsApi.get(runId),
  isDone,
  4000,
  10 * 60 * 1000,
)

async function loadResult() {
  resultLoading.value = true
  error.value = ''
  try {
    result.value = await forecastsApi.result(runId)
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
    message: `Yakin ingin menghapus forecast run "${forecastStore.currentRun?.name}"?`,
  })
  if (!ok) return
  try {
    await forecastsApi.remove(runId)
    ui.notify('success', 'Forecast run dihapus')
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

async function viewScenario(scenarioId: number) {
  const ok = await ui.askConfirmation({
    title: 'Jalankan Scenario',
    message: 'Jalankan ulang simulasi untuk scenario ini? Hasil akan ditampilkan setelah selesai.',
    confirmLabel: 'Jalankan',
    danger: false,
  })
  if (!ok) return
  try {
    const res = await forecastsApi.scenarioResults(runId, scenarioId)
    showScenarioResult(scenarioId, res)
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
  }
}

const scenarioModal = ref(false)
const scenarioResult = ref<Awaited<ReturnType<typeof forecastsApi.scenarioResults>> | null>(null)

function showScenarioResult(scenarioId: number, res: Awaited<ReturnType<typeof forecastsApi.scenarioResults>>) {
  scenarioResult.value = res
  scenarioModal.value = true
  void scenarioId
}

function openEvaluation() {
  router.push({ name: 'forecast-evaluation', params: { id: runId } })
}

watch(
  () => polling.data?.status,
  (status, oldStatus) => {
    forecastStore.currentRun = polling.data
    if (status === 'COMPLETED' && oldStatus !== 'COMPLETED') {
      loadResult()
      loadScenarios()
    } else if (status === 'FAILED') {
      polling.stop()
    }
  },
)

onMounted(async () => {
  forecastStore.currentRun = await forecastsApi.get(runId)
  polling.data = forecastStore.currentRun
  if (isDone(forecastStore.currentRun)) {
    if (forecastStore.currentRun.status === 'COMPLETED') await loadResult()
  } else {
    polling.start(false)
  }
  await loadScenarios()
})
</script>

<template>
  <div v-if="polling.data" class="space-y-6">
    <!-- Header -->
    <div class="card p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-lg font-semibold text-slate-900">{{ polling.data.name }}</h2>
            <BaseBadge :value="polling.data.forecast_type" />
            <BaseBadge :value="polling.data.status" />
          </div>
          <p class="mt-1 text-sm text-slate-500">
            Periode: {{ formatDate(polling.data.period_start) }} → {{ formatDate(polling.data.period_end) }} ·
            {{ formatNumber(polling.data.simulation_count) }} simulasi · Confidence {{ polling.data.confidence_level }}%
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button v-if="canManage" class="btn-secondary btn-sm" @click="openEvaluation">
            <LineChart class="h-3.5 w-3.5" /> Evaluasi
          </button>
          <button v-if="canManage" class="btn-primary btn-sm" @click="router.push({ name: 'scenario-new', params: { id: runId } })">
            <Plus class="h-3.5 w-3.5" /> Buat Scenario
          </button>
          <button v-if="canManage" class="btn-danger btn-sm" @click="removeRun">
            <Trash2 class="h-3.5 w-3.5" /> Hapus
          </button>
        </div>
      </div>

      <!-- Processing state -->
      <div
        v-if="polling.data.status === 'PENDING' || polling.data.status === 'PROCESSING'"
        class="mt-6 rounded-xl border border-blue-200 bg-blue-50 p-6 text-center"
      >
        <Loader2 class="mx-auto h-10 w-10 animate-spin text-blue-500" />
        <h3 class="mt-3 text-sm font-semibold text-blue-800">Simulasi Monte Carlo sedang berjalan</h3>
        <p class="mt-1 text-xs text-blue-600">
          {{ formatNumber(polling.data.simulation_count) }} iterasi diproses via queue. Halaman ini akan
          diperbarui otomatis setiap beberapa detik...
        </p>
        <p class="mt-3 text-xs text-blue-500">{{ formatDate(polling.data.created_at, true) }}</p>
      </div>

      <div
        v-else-if="polling.data.status === 'FAILED'"
        class="mt-6 rounded-xl border border-red-200 bg-red-50 p-6 text-center"
      >
        <h3 class="text-sm font-semibold text-red-800">Simulasi gagal</h3>
        <p class="mt-1 text-xs text-red-600">{{ polling.data.error_message || 'Terjadi kesalahan saat proses simulasi' }}</p>
      </div>

      <div v-else-if="polling.data.status === 'COMPLETED'" class="mt-4 flex flex-wrap items-center gap-4 text-sm text-slate-500">
        <span>Status: <span class="font-medium text-emerald-600">Selesai</span></span>
        <span v-if="polling.data.completed_at">Selesai pada: {{ formatDate(polling.data.completed_at, true) }}</span>
        <button class="btn-ghost btn-sm" @click="loadResult"><RefreshCw class="h-3.5 w-3.5" /> Muat ulang hasil</button>
      </div>
    </div>

    <!-- Result -->
    <template v-if="polling.data.status === 'COMPLETED'">
      <div v-if="resultLoading" class="card h-64 animate-pulse"></div>
      <div v-else-if="error" class="card p-6 text-sm text-red-600">{{ error }}</div>
      <template v-else-if="result">
        <div class="grid gap-4 lg:grid-cols-3">
          <div class="card p-5 lg:col-span-1">
            <h3 class="mb-4 text-sm font-semibold text-slate-800">Target Achievement</h3>
            <TargetProbability
              :probability="result.probability_target"
              :target="result.target_revenue ?? polling.data.target_revenue"
              :revenue="result.mean"
            />
            <div class="mt-5 border-t border-slate-100 pt-4">
              <PercentileTable :result="result" />
            </div>
          </div>
          <div class="card p-5 lg:col-span-2">
            <h3 class="mb-4 text-sm font-semibold text-slate-800">Distribusi Revenue (Monte Carlo)</h3>
            <DistributionChart :result="result" />
          </div>
        </div>

        <!-- Scenarios -->
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
            subtitle="Buat scenario untuk analisis what-if (ubah win rate / revenue)"
          >
            <template #icon><FlaskConical class="h-6 w-6" /></template>
          </EmptyState>
          <ul v-else class="divide-y divide-slate-100">
            <li v-for="s in forecastStore.scenarios" :key="s.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
              <div>
                <p class="text-sm font-medium text-slate-800">{{ s.name }}</p>
                <p class="text-xs text-slate-500">
                  Win rate: {{ s.win_rate_adjustment != null ? `${s.win_rate_adjustment}%` : '-' }} ·
                  Revenue: {{ s.revenue_adjustment != null ? `${s.revenue_adjustment}%` : '-' }} ·
                  Target: {{ s.target_revenue ? formatRupiah(s.target_revenue) : '-' }}
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

  <div v-else class="card p-10 text-center text-sm text-slate-500">Memuat forecast run...</div>

  <!-- Scenario result modal -->
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

      <div class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3">
        <p class="text-sm text-slate-600">Probabilitas Target (Scenario)</p>
        <p class="text-base font-bold text-primary-600">
          {{ scenarioResult.probability_target != null ? `${(scenarioResult.probability_target * 100).toFixed(1)}%` : '-' }}
        </p>
      </div>

      <div class="flex justify-end">
        <button class="btn-secondary" @click="scenarioModal = false">Tutup</button>
      </div>
    </div>
  </BaseModal>
</template>
