import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface Toast {
  id: number
  type: 'success' | 'error' | 'info'
  message: string
}

export interface ConfirmState {
  open: boolean
  title: string
  message: string
  confirmLabel: string
  danger: boolean
  resolve: ((value: boolean) => void) | null
}

let toastId = 0

export const useUiStore = defineStore('ui', () => {
  const sidebarOpen = ref(true)
  const toasts = ref<Toast[]>([])
  const confirm = ref<ConfirmState>({
    open: false,
    title: '',
    message: '',
    confirmLabel: 'Hapus',
    danger: true,
    resolve: null,
  })

  function notify(type: Toast['type'], message: string) {
    const id = ++toastId
    toasts.value.push({ id, type, message })
    setTimeout(() => dismiss(id), 4500)
  }

  function dismiss(id: number) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  function askConfirmation(opts: {
    title?: string
    message: string
    confirmLabel?: string
    danger?: boolean
  }): Promise<boolean> {
    return new Promise((resolve) => {
      confirm.value = {
        open: true,
        title: opts.title ?? 'Konfirmasi',
        message: opts.message,
        confirmLabel: opts.confirmLabel ?? 'Hapus',
        danger: opts.danger ?? true,
        resolve,
      }
    })
  }

  function closeConfirm(result: boolean) {
    confirm.value.resolve?.(result)
    confirm.value.open = false
    confirm.value.resolve = null
  }

  return { sidebarOpen, toasts, confirm, notify, dismiss, askConfirmation, closeConfirm }
})
