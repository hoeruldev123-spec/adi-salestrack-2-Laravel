<script setup lang="ts">
interface Props {
  rows?: number
  cols?: number
}
withDefaults(defineProps<Props>(), { rows: 4, cols: 4 })
</script>

<template>
  <div class="w-full">
    <div
      v-for="r in rows"
      :key="r"
      class="flex gap-4 border-b border-slate-100 px-4 py-3"
      :style="{ display: 'grid', gridTemplateColumns: `repeat(${cols}, 1fr)` }"
    >
      <div v-for="c in cols" :key="c" class="h-4 animate-pulse rounded bg-slate-200" />
    </div>
  </div>
</template>
