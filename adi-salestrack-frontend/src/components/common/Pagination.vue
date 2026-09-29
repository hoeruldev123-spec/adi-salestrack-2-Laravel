<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    currentPage: number
    lastPage: number
    total: number
    pages: number[]
  }>(),
  {},
)

const emit = defineEmits<{ change: [page: number] }>()
</script>

<template>
  <div v-if="lastPage > 0" class="flex items-center justify-between border-t border-slate-200 px-4 py-3">
    <p class="text-xs text-slate-500">
      Total {{ total }} data · Halaman {{ currentPage }} / {{ lastPage }}
    </p>
    <div class="flex items-center gap-1">
      <button
        class="btn-secondary btn-sm !px-2"
        :disabled="currentPage <= 1"
        @click="emit('change', currentPage - 1)"
      >
        <ChevronLeft class="h-3.5 w-3.5" />
      </button>
      <button
        v-for="p in pages"
        :key="p"
        class="btn-sm rounded-lg px-2.5 py-1.5 text-xs font-medium"
        :class="p === currentPage ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
        @click="emit('change', p)"
      >
        {{ p }}
      </button>
      <button
        class="btn-secondary btn-sm !px-2"
        :disabled="currentPage >= lastPage"
        @click="emit('change', currentPage + 1)"
      >
        <ChevronRight class="h-3.5 w-3.5" />
      </button>
    </div>
  </div>
</template>
