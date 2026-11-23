<script setup lang="ts">
import { computed } from 'vue'
import { useFormat } from '@/composables/useFormat'
import BaseChart from '@/components/charts/BaseChart.vue'
import type { ForecastDistribution } from '@/api/forecasts'

const props = defineProps<{ distribution: ForecastDistribution }>()
const { formatRupiah } = useFormat()

const histogram = computed(() => props.distribution.histogram ?? [])
const p10 = computed(() => Number(props.distribution.p10 ?? 0))
const p90 = computed(() => Number(props.distribution.p90 ?? props.distribution.maximum ?? 0))

const chartConfig = computed(() => ({
  type: 'bar' as const,
  data: {
    labels: histogram.value.map((b) => formatRupiah(b.bin_start)),
    datasets: [
      {
        label: 'Frekuensi',
        data: histogram.value.map((b) => b.count),
        backgroundColor: (context: { dataIndex: number }) => {
          const b = histogram.value[context.dataIndex]
          const inRange = b && b.bin_end >= p10.value && b.bin_start <= p90.value
          return inRange ? 'rgba(79, 70, 229, 0.9)' : 'rgba(148, 163, 184, 0.5)'
        },
        borderRadius: 2,
      },
    ],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          title: (items: Array<{ dataIndex: number }>) => {
            const b = histogram.value[items[0]?.dataIndex]
            return b ? `${formatRupiah(b.bin_start)} - ${formatRupiah(b.bin_end)}` : ''
          },
        },
      },
    },
    scales: {
      x: { ticks: { maxRotation: 45, font: { size: 10 } } },
      y: { beginAtZero: true, ticks: { font: { size: 10 } } },
    },
  },
}))
</script>

<template>
  <BaseChart v-if="histogram.length" :config="chartConfig" :height="320" />
  <p v-else class="py-16 text-center text-sm text-slate-500">Distribusi belum tersedia</p>
  <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
    <span><span class="font-medium text-primary-600">■</span> Rentang P10–P90</span>
    <span><span class="font-medium text-slate-400">■</span> Di luar rentang</span>
  </div>
</template>
