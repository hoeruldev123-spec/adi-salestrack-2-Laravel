<script setup lang="ts">
import { computed } from 'vue'
import { badgeColor } from '@/constants'

const props = withDefaults(
  defineProps<{
    value?: string | null
    size?: 'xs' | 'sm'
  }>(),
  { value: null, size: 'sm' },
)

const cls = computed(() => [
  'inline-flex items-center rounded-full font-medium capitalize',
  badgeColor(props.value),
  props.size === 'xs' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-0.5 text-xs',
])
</script>

<template>
  <span :class="cls"><slot>{{ value }}</slot></span>
</template>
