<script setup lang="ts">
withDefaults(
  defineProps<{
    title?: string
    subtitle?: string
    actionLabel?: string
  }>(),
  { title: '', subtitle: '', actionLabel: '' },
)

const emit = defineEmits<{ action: [] }>()
</script>

<template>
  <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-16 text-center">
    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-200 text-slate-500">
      <slot name="icon" />
    </div>
    <h3 v-if="title" class="text-sm font-semibold text-slate-800">{{ title }}</h3>
    <p v-if="subtitle" class="mt-1 text-sm text-slate-500">{{ subtitle }}</p>
    <button v-if="actionLabel" class="btn-primary mt-4" @click="emit('action')">{{ actionLabel }}</button>
    <slot />
  </div>
</template>
