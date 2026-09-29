<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { LogIn } from 'lucide-vue-next'
import type { AxiosError } from 'axios'

const auth = useAuthStore()
const ui = useUiStore()
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
  if (!email.value || !password.value) {
    error.value = 'Email dan password wajib diisi'
    return
  }
  loading.value = true
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    ui.notify('success', `Selamat datang, ${auth.user?.name}!`)
    const redirect = route.query.redirect as string | undefined
    router.push(redirect || '/')
  } catch (e) {
    const err = e as AxiosError<{ message?: string; errors?: Record<string, string[]> }>
    const data = err.response?.data
    error.value = data?.message || (data?.errors ? Object.values(data.errors).flat().join(', ') : 'Login gagal. Periksa kembali email dan password Anda')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-900 px-4">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-600 text-xl font-bold text-white">AS</div>
        <h1 class="text-2xl font-semibold text-white">ADI SalesTrack 2.0</h1>
        <p class="mt-1 text-sm text-slate-400">Sales Management, Analytics & Revenue Forecasting</p>
      </div>

      <form class="card !bg-slate-800 !border-slate-700 p-6" @submit.prevent="submit">
        <h2 class="mb-5 text-lg font-semibold text-white">Masuk ke Akun Anda</h2>

        <div v-if="error" class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-400">
          {{ error }}
        </div>

        <div class="mb-4">
          <label class="label !text-slate-300" for="email">Email</label>
          <input
            id="email"
            v-model="email"
            type="email"
            autocomplete="email"
            class="input !border-slate-600 !bg-slate-900 !text-white"
            placeholder="nama@perusahaan.com"
          />
        </div>

        <div class="mb-6">
          <label class="label !text-slate-300" for="password">Password</label>
          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="current-password"
            class="input !border-slate-600 !bg-slate-900 !text-white"
            placeholder="••••••••"
          />
        </div>

        <button type="submit" class="btn-primary w-full" :disabled="loading">
          <LogIn class="h-4 w-4" />
          {{ loading ? 'Memproses...' : 'Masuk' }}
        </button>
      </form>

      <p class="mt-6 text-center text-xs text-slate-500">
        © {{ new Date().getFullYear() }} ADI SalesTrack · Internal Application
      </p>
    </div>
  </div>
</template>
