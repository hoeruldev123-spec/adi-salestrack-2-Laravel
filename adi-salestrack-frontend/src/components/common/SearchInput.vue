<script setup lang="ts">
import { Search } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    modelValue: string
    placeholder?: string
    debounce?: number
  }>(),
  { placeholder: 'Cari...', debounce: 400 },
)

const emit = defineEmits<{ 'update:modelValue': [value: string]; search: [] }>()

let timer: ReturnType<typeof setTimeout> | null = null

function onInput(e: Event) {
  const val = (e.target as HTMLInputElement).value
  emit('update:modelValue', val)
  if (timer) clearTimeout(timer)
  timer = setTimeout(() => emit('search'), props.debounce)
}
</script>

<template>
  <div class="relative">
    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
    <input :value="modelValue" class="input !pl-9" :placeholder="placeholder" @input="onInput" />
  </div>
</template>
