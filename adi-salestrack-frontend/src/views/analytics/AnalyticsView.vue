<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { analyticsApi, type PipelineData } from '@/api/analytics'
import { useFormat } from '@/composables/useFormat'
import { useUiStore } from '@/stores/ui'
import { AVAILABLE_YEARS } from '@/constants'
import BaseChart from '@/components/charts/BaseChart.vue'
import KpiCard from '@/components/analytics/KpiCard.vue'
import { Layers, CircleCheck, Percent, Banknote } from 'lucide-vue-next'

const { formatRupiah, formatPercent } = useFormat()
const ui = useUiStore()

const year = ref<number | 'ALL'>('ALL')
const revenue = ref<Awaited<ReturnType<typeof analyticsApi.revenue>> | null>(null)
const pipeline = ref<PipelineData | null>(null)
const performance = ref<Awaited<ReturnType<typeof analyticsApi.performance>> | null>(null)
const loading = ref(true)

const totalRevenue = computed(() => {
  if (revenue.value?.total !== undefined) return revenue.value.total
  return (revenue.value?.data ?? []).reduce((acc, d) => acc + d.revenue, 0)
})

async function load() {
  loading.value = true
  try {
    const [rev, pipe, perf] = await Promise.all([
      analyticsApi.revenue({ year: year.value }),
      analyticsApi.pipeline({ year: year.value }),
      analyticsApi.performance({ year: year.value }),
    ])
    revenue.value = rev
    pipeline.value = pipe
    performance.value = perf
  } catch (e) {
    ui.notify('error', (e as Error).message)
  } finally {
    loading.value = false
  }
}

const revenueChart = computed(() => ({
  type: 'bar' as const,
  data: {
    labels: (revenue.value?.data ?? []).map((d) => d.period),
    datasets: [
      {
        label: 'Revenue',
        data: (revenue.value?.data ?? []).map((d) => d.revenue),
        backgroundColor: 'rgba(16, 185, 129, 0.8)',
        borderRadius: 6,
      },
    ],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { ticks: { callback: (v: number | string) => `${Number(v) / 1_000_000}jt` } } },
  },
}))

const pipelineChart = computed(() => ({
  type: 'bar' as const,
  data: {
    labels: (pipeline.value?.by_stage ?? []).map((s) => s.stage),
    datasets: [
      {
        label: 'Nilai Pipeline',
        data: (pipeline.value?.by_stage ?? []).map((s) => s.value),
        backgroundColor: '#6366f1',
        borderRadius: 6,
      },
    ],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    indexAxis: 'y' as const,
    scales: { x: { ticks: { callback: (v: number | string) => `${Number(v) / 1_000_000}jt` } } },
  },
}))

const breakdownCharts = computed(() => {
  if (!revenue.value?.breakdown) return []
  return Object.entries(revenue.value.breakdown).map(([label, rows]) => ({
    label,
    config: {
      type: 'bar' as const,
      data: {
        labels: rows.map((r) => r.label),
        datasets: [
          {
            label,
            data: rows.map((r) => r.value),
            backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#8b5cf6', '#06b6d4', '#ef4444'],
            borderRadius: 6,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        indexAxis: 'y' as const,
        scales: { x: { ticks: { callback: (v: number | string) => `${Number(v) / 1_000_000}jt` } } },
      },
    },
  }))
})

const performanceChart = computed(() => ({
  type: 'bar' as const,
  data: {
    labels: (performance.value?.sales ?? []).map((s) => s.name),
    datasets: [
      {
        label: 'Revenue',
        data: (performance.value?.sales ?? []).map((s) => s.revenue),
        backgroundColor: 'rgba(245, 158, 11, 0.8)',
        borderRadius: 6,
      },
    ],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    indexAxis: 'y' as const,
    scales: { x: { ticks: { callback: (v: number | string) => `${Number(v) / 1_000_000}jt` } } },
  },
}))

const breakdownLabel: Record<string, string> = {
  principal: 'Breakdown by Principal',
  customer: 'Breakdown by Customer',
  product: 'Breakdown by Product',
  salesperson: 'Breakdown by Salesperson',
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <label class="text-sm font-medium text-slate-700">Filter Periode:</label>
        <select v-model="year" class="input !w-44" @change="load">
          <option value="ALL">Semua Periode</option>
          <option v-for="y in AVAILABLE_YEARS" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
      <KpiCard title="Total Revenue" :value="formatRupiah(totalRevenue)" :loading="loading" :icon="Banknote" color="emerald" />
      <KpiCard title="Total Pipeline" :value="formatRupiah(pipeline?.total_pipeline)" :loading="loading" :icon="Layers" color="indigo" />
      <KpiCard title="Open Deals" :value="String(pipeline?.open_count ?? '-')" :loading="loading" :icon="Layers" color="blue" />
      <KpiCard title="Won Deals" :value="String(pipeline?.won_count ?? '-')" :loading="loading" :icon="CircleCheck" color="cyan" />
      <KpiCard title="Win Rate" :value="formatPercent(pipeline?.win_rate)" :loading="loading" :icon="Percent" color="purple" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <div class="card p-5">
        <h3 class="mb-4 text-sm font-semibold text-slate-800">Revenue {{ year === 'ALL' ? '(Semua Periode)' : year }}</h3>
        <BaseChart v-if="(revenue?.data ?? []).length" :config="revenueChart" :height="280" />
        <p v-else-if="!loading" class="py-16 text-center text-sm text-slate-500">Belum ada data revenue</p>
      </div>
      <div class="card p-5">
        <h3 class="mb-4 text-sm font-semibold text-slate-800">Pipeline per Stage</h3>
        <BaseChart v-if="(pipeline?.by_stage ?? []).length" :config="pipelineChart" :height="280" />
        <p v-else-if="!loading" class="py-16 text-center text-sm text-slate-500">Belum ada data pipeline</p>
      </div>
    </div>

    <div v-if="breakdownCharts.length" class="grid gap-4 lg:grid-cols-2">
      <div v-for="bc in breakdownCharts" :key="bc.label" class="card p-5">
        <h3 class="mb-4 text-sm font-semibold text-slate-800">{{ breakdownLabel[bc.label] ?? bc.label }}</h3>
        <BaseChart :config="bc.config" :height="260" />
      </div>
    </div>

    <div class="card p-5">
      <h3 class="mb-4 text-sm font-semibold text-slate-800">Performa Sales {{ year === 'ALL' ? '(Semua Periode)' : year }}</h3>
      <BaseChart v-if="(performance?.sales ?? []).length" :config="performanceChart" :height="Math.max(260, (performance?.sales?.length ?? 0) * 48)" />
      <p v-else-if="!loading" class="py-16 text-center text-sm text-slate-500">Belum ada data performa</p>
    </div>

    <div v-if="(performance?.sales ?? []).length" class="card overflow-hidden">
      <div class="card-header"><h3 class="text-sm font-semibold text-slate-800">Detail Performa</h3></div>
      <div class="overflow-x-auto">
        <table class="table-base">
          <thead>
            <tr>
              <th>Sales</th>
              <th class="text-right">Revenue</th>
              <th class="text-right">Opportunities</th>
              <th class="text-right">Won</th>
              <th class="text-right">Win Rate</th>
              <th class="text-right">Avg Deal Size</th>
              <th class="text-right">Avg Cycle (hari)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in performance?.sales ?? []" :key="s.user_id">
              <td class="font-medium">{{ s.name }}</td>
              <td class="text-right">{{ formatRupiah(s.revenue) }}</td>
              <td class="text-right">{{ s.opportunities_count }}</td>
              <td class="text-right">{{ s.win_count }}</td>
              <td class="text-right">{{ formatPercent(s.win_rate) }}</td>
              <td class="text-right">{{ formatRupiah(s.average_deal_size) }}</td>
              <td class="text-right">{{ s.average_sales_cycle_days ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
