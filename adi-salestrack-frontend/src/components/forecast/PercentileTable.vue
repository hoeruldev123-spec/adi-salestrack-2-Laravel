<script setup lang="ts">
import { computed } from 'vue'
import { useFormat } from '@/composables/useFormat'
import type { ForecastResultSummary } from '@/api/forecasts'

const props = defineProps<{ result: ForecastResultSummary }>()
const { formatRupiah } = useFormat()

const rows = computed(() => [
  { key: 'P10', value: props.result.p10 },
  { key: 'P25', value: props.result.p25 },
  { key: 'P50', value: props.result.p50 },
  { key: 'P75', value: props.result.p75 },
  { key: 'P90', value: props.result.p90 },
  { key: 'P95', value: props.result.p95 },
])

const summary = computed(() => [
  { key: 'Min', value: props.result.minimum },
  { key: 'Max', value: props.result.maximum },
  { key: 'Mean', value: props.result.mean },
  { key: 'Median', value: props.result.median },
])

function percentileWidth(value: number): string {
  const max = Number(props.result.p95 ?? props.result.maximum ?? value)
  const min = Number(props.result.minimum ?? 0)
  const range = max - min || 1
  return `${Math.max(2, ((value - min) / range) * 100)}%`
}
</script>

<template>
  <div class="space-y-4">
    <div class="grid grid-cols-4 gap-2">
      <div v-for="s in summary" :key="s.key" class="rounded-lg bg-slate-50 p-3 text-center">
        <p class="text-[11px] uppercase tracking-wide text-slate-500">{{ s.key }}</p>
        <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatRupiah(s.value) }}</p>
      </div>
    </div>

    <div>
      <p class="label">Percentile</p>
      <div class="space-y-2">
        <div v-for="r in rows" :key="r.key" class="flex items-center gap-3">
          <span class="w-8 text-xs font-medium text-slate-500">{{ r.key }}</span>
          <div class="h-2 flex-1 rounded-full bg-slate-100">
            <div class="h-2 rounded-full bg-primary-500" :style="{ width: percentileWidth(r.value) }"></div>
          </div>
          <span class="w-28 text-right text-xs font-medium text-slate-700">{{ formatRupiah(r.value) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
