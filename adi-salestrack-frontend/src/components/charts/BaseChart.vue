<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Chart from 'chart.js/auto'
import type { ChartConfiguration } from 'chart.js'

const props = withDefaults(
  defineProps<{
    config: ChartConfiguration
    height?: number
  }>(),
  { height: 300 },
)

const canvasRef = ref<HTMLCanvasElement | null>(null)
let chart: Chart | null = null

function render() {
  if (!canvasRef.value) return
  chart?.destroy()
  chart = new Chart(canvasRef.value, props.config)
}

onMounted(render)
watch(
  () => props.config,
  () => render(),
  { deep: true },
)
onBeforeUnmount(() => chart?.destroy())
</script>

<template>
  <div>
    <canvas ref="canvasRef" :style="{ height: `${height}px` }"></canvas>
  </div>
</template>
