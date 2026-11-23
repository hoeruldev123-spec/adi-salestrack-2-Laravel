<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { analyticsApi } from '@/api/analytics'
import { useFormat } from '@/composables/useFormat'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import {
  Banknote,
  Layers,
  Trophy,
  Percent,
  Briefcase,
  TrendingUp,
  Target,
} from 'lucide-vue-next'
import { AVAILABLE_YEARS } from '@/constants'
import BaseChart from '@/components/charts/BaseChart.vue'
import KpiCard from '@/components/analytics/KpiCard.vue'
import BaseBadge from '@/components/common/BaseBadge.vue'
import type { DashboardData } from '@/api/analytics'

const { formatRupiah, formatPercent } = useFormat()
const ui = useUiStore()
const router = useRouter()
const auth = useAuthStore()

const year = ref<number | 'ALL'>('ALL')
const loading = ref(true)
const data = ref<DashboardData | null>(null)

async function load() {
  loading.value = true
  try {
    data.value = await analyticsApi.dashboard(year.value)
  } catch (e) {
    ui.notify('error', (e as Error).message)
  } finally {
    loading.value = false
  }
}

const revenueChart = computed(() => ({
  type: 'bar' as const,
  data: {
    labels: (data.value?.monthly_revenue ?? []).map((m) => m.month || (m as any).period),
    datasets: [
      {
        label: 'Revenue',
        data: (data.value?.monthly_revenue ?? []).map((m) => m.revenue),
        backgroundColor: 'rgba(79, 70, 229, 0.8)',
        borderRadius: 6,
      },
    ],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { ticks: { callback: (v: number | string) => `${Number(v) / 1_000_000}jt` } },
    },
  },
}))

const pipelineChart = computed(() => ({
  type: 'bar' as const,
  data: {
    labels: (data.value?.pipeline_by_stage ?? []).map((s) => s.stage),
    datasets: [
      {
        label: 'Nilai Pipeline',
        data: (data.value?.pipeline_by_stage ?? []).map((s) => s.value),
        backgroundColor: ['#94a3b8', '#3b82f6', '#f59e0b', '#a855f7', '#06b6d4'],
        borderRadius: 6,
      },
    ],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { ticks: { callback: (v: number | string) => `${Number(v) / 1_000_000}jt` } },
    },
  },
}))

function openOpportunity(id: number) {
  router.push({ name: 'opportunity-detail', params: { id } })
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">Halo, {{ auth.user?.name?.split(' ')[0] }}!</h2>
        <p class="text-sm text-slate-500">Ringkasan performa penjualan Anda.</p>
      </div>
      <div class="flex items-center gap-2">
        <label class="text-sm font-medium text-slate-700">Periode:</label>
        <select v-model="year" class="input !w-44" @change="load">
          <option value="ALL">Semua Periode</option>
          <option v-for="y in AVAILABLE_YEARS" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <KpiCard title="Total Revenue" :value="formatRupiah(data?.total_revenue)" :loading="loading" :icon="Banknote" color="emerald" />
      <KpiCard title="Total Pipeline" :value="formatRupiah(data?.total_pipeline)" :loading="loading" :icon="Layers" color="indigo" />
      <KpiCard title="Won Revenue" :value="formatRupiah(data?.won_revenue)" :loading="loading" :icon="Trophy" color="cyan" />
      <KpiCard title="Win Rate" :value="formatPercent(data?.win_rate)" :loading="loading" :icon="Percent" color="purple" />
    </div>
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
      <KpiCard title="Average Deal Size" :value="formatRupiah(data?.average_deal_size)" :loading="loading" :icon="Briefcase" color="blue" />
      <KpiCard title="Forecast Revenue" :value="formatRupiah(data?.forecast_revenue)" :loading="loading" :icon="TrendingUp" color="amber" />
      <KpiCard title="Target Probability" :value="formatPercent(data?.target_probability)" :loading="loading" :icon="Target" color="indigo" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <div class="card p-5">
        <h3 class="mb-4 text-sm font-semibold text-slate-800">Revenue {{ year === 'ALL' ? '(Semua Periode)' : `Bulanan ${year}` }}</h3>
        <BaseChart v-if="data?.monthly_revenue?.length" :config="revenueChart" :height="280" />
        <p v-else-if="!loading" class="py-16 text-center text-sm text-slate-500">Belum ada data revenue</p>
      </div>
      <div class="card p-5">
        <h3 class="mb-4 text-sm font-semibold text-slate-800">Pipeline per Stage</h3>
        <BaseChart v-if="data?.pipeline_by_stage?.length" :config="pipelineChart" :height="280" />
        <p v-else-if="!loading" class="py-16 text-center text-sm text-slate-500">Belum ada data pipeline</p>
      </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <div class="card">
        <div class="card-header">
          <h3 class="text-sm font-semibold text-slate-800">Opportunity Terbaru</h3>
          <button class="btn-ghost btn-sm" @click="router.push('/opportunities')">Lihat semua</button>
        </div>
        <div v-if="(data?.recent_opportunities ?? []).length === 0 && !loading" class="py-10 text-center text-sm text-slate-500">
          Belum ada opportunity
        </div>
        <ul v-else class="divide-y divide-slate-100">
          <li v-for="op in (data?.recent_opportunities ?? []).slice(0, 5)" :key="op.id" class="flex items-center justify-between px-5 py-3 hover:bg-slate-50">
            <button class="min-w-0 text-left" @click="openOpportunity(op.id)">
              <p class="truncate text-sm font-medium text-slate-800 hover:text-primary-600">{{ op.title }}</p>
              <p class="text-xs text-slate-500">{{ op.company?.name ?? 'Tanpa company' }}</p>
            </button>
            <div class="flex shrink-0 items-center gap-3">
              <BaseBadge :value="op.stage" size="xs" />
              <span class="text-sm font-semibold text-slate-800">{{ formatRupiah(op.value) }}</span>
            </div>
          </li>
        </ul>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="text-sm font-semibold text-slate-800">Top Sales</h3>
        </div>
        <div v-if="(data?.top_sales ?? []).length === 0 && !loading" class="py-10 text-center text-sm text-slate-500">
          Belum ada data sales
        </div>
        <ul v-else class="divide-y divide-slate-100">
          <li v-for="s in (data?.top_sales ?? []).slice(0, 5)" :key="s.user_id" class="flex items-center justify-between px-5 py-3">
            <div class="flex min-w-0 items-center gap-3">
              <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700">
                {{ s.name?.charAt(0) }}
              </div>
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-slate-800">{{ s.name }}</p>
                <p class="text-xs text-slate-500">{{ s.win_count ?? 0 }} won · {{ formatPercent(s.win_rate) }} win rate</p>
              </div>
            </div>
            <span class="shrink-0 text-sm font-semibold text-slate-800">{{ formatRupiah(s.revenue) }}</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
