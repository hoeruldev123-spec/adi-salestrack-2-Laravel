<script setup lang="ts">
import { X } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    open: boolean
    title?: string
    width?: string
  }>(),
  { title: '', width: 'max-w-lg' },
)

const emit = defineEmits<{ close: [] }>()
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-150"
      enter-from-class="opacity-0"
      leave-active-class="transition-opacity duration-150"
      leave-to-class="opacity-0"
    >
      <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 pt-16" @click.self="emit('close')">
        <Transition
          enter-active-class="transition-transform duration-150"
          enter-from-class="scale-95 opacity-0"
          leave-active-class="transition-transform duration-150"
          leave-to-class="scale-95 opacity-0"
        >
          <div v-if="open" class="w-full rounded-xl bg-white shadow-xl" :class="width">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
              <h3 class="text-base font-semibold text-slate-900">{{ title }}</h3>
              <button class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" @click="emit('close')">
                <X class="h-5 w-5" />
              </button>
            </div>
            <div class="px-5 py-4">
              <slot />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
