<script setup lang="ts">
import { useUiStore } from '@/stores/ui'
import { CheckCircle2, XCircle, Info, X } from 'lucide-vue-next'

const ui = useUiStore()

const icons = { success: CheckCircle2, error: XCircle, info: Info }
const colors = {
  success: 'text-emerald-500',
  error: 'text-red-500',
  info: 'text-blue-500',
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed right-4 top-4 z-[70] flex w-80 flex-col gap-2">
      <TransitionGroup
        enter-active-class="transition-all duration-200"
        enter-from-class="translate-x-4 opacity-0"
        leave-active-class="transition-all duration-200"
        leave-to-class="translate-x-4 opacity-0"
      >
        <div
          v-for="toast in ui.toasts"
          :key="toast.id"
          class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white p-3 shadow-lg"
        >
          <component :is="icons[toast.type]" class="mt-0.5 h-5 w-5 shrink-0" :class="colors[toast.type]" />
          <p class="flex-1 text-sm text-slate-700">{{ toast.message }}</p>
          <button class="text-slate-400 hover:text-slate-600" @click="ui.dismiss(toast.id)">
            <X class="h-4 w-4" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
