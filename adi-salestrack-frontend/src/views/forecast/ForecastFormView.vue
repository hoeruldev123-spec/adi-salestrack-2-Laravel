<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useForecastStore } from '@/stores/forecast'
import { useUiStore } from '@/stores/ui'
import { getApiErrorMessage, getFieldError } from '@/utils/form'
import { FORECAST_TYPES } from '@/constants'

const router = useRouter()
const forecastStore = useForecastStore()
const ui = useUiStore()

const saving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})

const form = reactive({
  name: '',
  forecast_type: 'HISTORICAL',
  period_start: '',
  period_end: '',
  simulation_count: 10000,
  confidence_level: 90,
  target_revenue: null as number | null,
})

const simCountLabel = computed(() => form.simulation_count.toLocaleString('id-ID'))

async function save() {
  saving.value = true
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  try {
    const run = await forecastStore.createRun({
      name: form.name,
      forecast_type: form.forecast_type,
      period_start: form.period_start || null,
      period_end: form.period_end || null,
      simulation_count: form.simulation_count,
      confidence_level: form.confidence_level,
      target_revenue: form.target_revenue,
    })
    ui.notify('success', 'Forecast run dibuat. Simulasi sedang diproses.')
    router.push({ name: 'forecast-detail', params: { id: run.id } })
  } catch (e) {
    for (const f of ['name', 'forecast_type', 'period_start', 'period_end', 'simulation_count', 'confidence_level', 'target_revenue']) {
      const msg = getFieldError(e, f)
      if (msg) fieldErrors[f] = msg
    }
    if (!Object.keys(fieldErrors).length) ui.notify('error', getApiErrorMessage(e))
  } finally {
    saving.value = false
  }
}

onMounted(() => {})
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-4">
    <div class="card p-6">
      <h2 class="mb-5 text-base font-semibold text-slate-900">Buat Forecast Run (Monte Carlo)</h2>
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="label">Nama Run *</label>
          <input v-model="form.name" class="input" placeholder="Forecast Q1 2026" />
          <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">{{ fieldErrors.name }}</p>
        </div>

        <div>
          <label class="label">Tipe Forecast *</label>
          <select v-model="form.forecast_type" class="input">
            <option v-for="t in FORECAST_TYPES" :key="t" :value="t">{{ t }}</option>
          </select>
          <p v-if="fieldErrors.forecast_type" class="mt-1 text-xs text-red-600">{{ fieldErrors.forecast_type }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label">Periode Mulai</label>
            <input v-model="form.period_start" type="date" class="input" />
            <p v-if="fieldErrors.period_start" class="mt-1 text-xs text-red-600">{{ fieldErrors.period_start }}</p>
          </div>
          <div>
            <label class="label">Periode Selesai</label>
            <input v-model="form.period_end" type="date" class="input" />
            <p v-if="fieldErrors.period_end" class="mt-1 text-xs text-red-600">{{ fieldErrors.period_end }}</p>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label">Jumlah Simulasi *</label>
            <input v-model.number="form.simulation_count" type="number" min="1000" step="1000" class="input" />
            <p class="mt-1 text-xs text-slate-500">{{ simCountLabel }} iterasi</p>
            <p v-if="fieldErrors.simulation_count" class="mt-1 text-xs text-red-600">{{ fieldErrors.simulation_count }}</p>
          </div>
          <div>
            <label class="label">Confidence Level (%) *</label>
            <input v-model.number="form.confidence_level" type="number" min="50" max="99" class="input" />
            <p v-if="fieldErrors.confidence_level" class="mt-1 text-xs text-red-600">{{ fieldErrors.confidence_level }}</p>
          </div>
        </div>

        <div>
          <label class="label">Target Revenue</label>
          <input v-model.number="form.target_revenue" type="number" min="0" class="input" placeholder="Rp..." />
          <p class="mt-1 text-xs text-slate-500">Digunakan untuk menghitung probabilitas pencapaian target</p>
          <p v-if="fieldErrors.target_revenue" class="mt-1 text-xs text-red-600">{{ fieldErrors.target_revenue }}</p>
        </div>

        <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-xs text-blue-700">
          Simulasi diproses secara asinkron. Anda akan diarahkan ke halaman detail untuk memantau status
          hingga selesai ({{ simCountLabel }} iterasi dapat memakan waktu beberapa menit).
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="router.back()">Batal</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Membuat run...' : 'Jalankan Simulasi' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>
