<script setup lang="ts">
import { computed } from 'vue'
import { useFormat } from '@/composables/useFormat'

const props = withDefaults(
  defineProps<{
    probability: number | null
    target?: number | null
    revenue?: number | null
  }>(),
  { probability: null, target: null, revenue: null },
)

const { formatRupiah, formatPercent } = useFormat()

const pct = computed(() => Math.min(100, Math.max(0, props.probability || 0)))

const color = computed(() => {
  if (pct.value >= 70) return 'bg-emerald-500'
  if (pct.value >= 40) return 'bg-amber-500'
  return 'bg-red-500'
})

const textColor = computed(() => {
  if (pct.value >= 70) return 'text-emerald-600'
  if (pct.value >= 40) return 'text-amber-600'
  return 'text-red-600'
})
</script>

<template>
  <div class="space-y-2">
    <div class="flex items-end justify-between">
      <div>
        <p class="text-sm font-semibold" :class="textColor">{{ formatPercent(pct, 0) }}</p>
        <p class="text-xs text-slate-500">Probabilitas mencapai target</p>
      </div>
      <div v-if="target" class="text-right">
        <p class="text-xs text-slate-500">Target: {{ formatRupiah(target) }}</p>
        <p v-if="revenue" class="text-xs text-slate-500">Forecast: {{ formatRupiah(revenue) }}</p>
      </div>
    </div>
    <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100">
      <div class="h-full rounded-full transition-all duration-700" :class="color" :style="{ width: `${pct}%` }"></div>
    </div>
  </div>
</template>
