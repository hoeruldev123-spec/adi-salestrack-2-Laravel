<script setup lang="ts">
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
</script>

<template>
  <Teleport to="body">
    <div v-if="ui.confirm.open" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 p-4">
      <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl">
        <h3 class="text-base font-semibold text-slate-900">{{ ui.confirm.title }}</h3>
        <p class="mt-2 text-sm text-slate-600">{{ ui.confirm.message }}</p>
        <div class="mt-5 flex justify-end gap-2">
          <button class="btn-secondary" @click="ui.closeConfirm(false)">Batal</button>
          <button class="btn-danger" @click="ui.closeConfirm(true)">{{ ui.confirm.confirmLabel }}</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
