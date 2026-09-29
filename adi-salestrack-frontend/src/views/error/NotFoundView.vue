<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center bg-slate-100 px-4">
    <p class="text-7xl font-bold text-primary-600">404</p>
    <h1 class="mt-4 text-xl font-semibold text-slate-900">Halaman Tidak Ditemukan</h1>
    <p class="mt-2 text-sm text-slate-500">Halaman yang Anda cari tidak ada atau telah dipindahkan.</p>
    <button class="btn-primary mt-6" @click="router.push('/')">Kembali ke Dashboard</button>
  </div>
</template>
