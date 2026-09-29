<script setup lang="ts">
import type { Component } from 'vue'

withDefaults(
  defineProps<{
    title: string
    value: string
    icon?: Component
    color?: 'indigo' | 'emerald' | 'blue' | 'amber' | 'purple' | 'cyan'
    hint?: string
    loading?: boolean
  }>(),
  { color: 'indigo', loading: false },
)

const colorMap: Record<string, string> = {
  indigo: 'bg-indigo-100 text-indigo-600',
  emerald: 'bg-emerald-100 text-emerald-600',
  blue: 'bg-blue-100 text-blue-600',
  amber: 'bg-amber-100 text-amber-600',
  purple: 'bg-purple-100 text-purple-600',
  cyan: 'bg-cyan-100 text-cyan-600',
}
</script>

<template>
  <div class="card p-5">
    <div class="flex items-start justify-between">
      <div class="min-w-0">
        <p class="text-sm font-medium text-slate-500">{{ title }}</p>
        <p v-if="loading" class="mt-2 h-7 w-28 animate-pulse rounded bg-slate-200"></p>
        <p v-else class="mt-1 truncate text-2xl font-bold text-slate-900">{{ value }}</p>
        <p v-if="hint && !loading" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
      </div>
      <div v-if="icon" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="colorMap[color]">
        <component :is="icon" class="h-5 w-5" />
      </div>
    </div>
  </div>
</template>
