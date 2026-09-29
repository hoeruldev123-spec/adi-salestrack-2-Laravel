<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { forecastsApi } from '@/api/forecasts'
import { useUiStore } from '@/stores/ui'
import { useFormat } from '@/composables/useFormat'
import { getApiErrorMessage, getFieldError } from '@/utils/form'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const { formatRupiah } = useFormat()

const runId = Number(route.params.id)
const runName = ref('')
const loading = ref(true)
const saving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})

const form = reactive({
  name: '',
  win_rate_adjustment: null as number | null,
  revenue_adjustment: null as number | null,
  target_revenue: null as number | null,
})

const preview = computed(() => {
  const parts: string[] = []
  if (form.win_rate_adjustment != null) parts.push(`win rate ${form.win_rate_adjustment >= 0 ? '+' : ''}${form.win_rate_adjustment}%`)
  if (form.revenue_adjustment != null) parts.push(`revenue ${form.revenue_adjustment >= 0 ? '+' : ''}${form.revenue_adjustment}%`)
  if (form.target_revenue != null) parts.push(`target ${formatRupiah(form.target_revenue)}`)
  return parts.length ? parts.join(' · ') : 'Belum ada penyesuaian'
})

async function save() {
  saving.value = true
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  try {
    await forecastsApi.createScenario(runId, {
      name: form.name,
      win_rate_adjustment: form.win_rate_adjustment,
      revenue_adjustment: form.revenue_adjustment,
      target_revenue: form.target_revenue,
    })
    ui.notify('success', 'Scenario berhasil dibuat')
    router.push({ name: 'forecast-detail', params: { id: runId } })
  } catch (e) {
    for (const f of ['name', 'win_rate_adjustment', 'revenue_adjustment', 'target_revenue']) {
      const msg = getFieldError(e, f)
      if (msg) fieldErrors[f] = msg
    }
    if (!Object.keys(fieldErrors).length) ui.notify('error', getApiErrorMessage(e))
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    const run = await forecastsApi.get(runId)
    runName.value = run.name
    form.target_revenue = run.target_revenue ?? null
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
    router.back()
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="mx-auto max-w-xl space-y-4">
    <div class="card p-6">
      <h2 class="text-base font-semibold text-slate-900">Buat Scenario (What-If)</h2>
      <p class="mt-1 text-sm text-slate-500">Untuk forecast run: {{ runName || `#${runId}` }}</p>

      <form v-if="!loading" class="mt-5 space-y-4" @submit.prevent="save">
        <div>
          <label class="label">Nama Scenario *</label>
          <input v-model="form.name" class="input" placeholder="Optimistic Q1" />
          <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">{{ fieldErrors.name }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label">Adjust Win Rate (%)</label>
            <input v-model.number="form.win_rate_adjustment" type="number" step="1" class="input" placeholder="mis. +10 atau -5" />
            <p class="mt-1 text-xs text-slate-500">Penyesuaian relatif terhadap win rate baseline</p>
            <p v-if="fieldErrors.win_rate_adjustment" class="mt-1 text-xs text-red-600">{{ fieldErrors.win_rate_adjustment }}</p>
          </div>
          <div>
            <label class="label">Adjust Revenue (%)</label>
            <input v-model.number="form.revenue_adjustment" type="number" step="1" class="input" placeholder="mis. +15" />
            <p class="mt-1 text-xs text-slate-500">Penyesuaian relatif terhadap revenue</p>
            <p v-if="fieldErrors.revenue_adjustment" class="mt-1 text-xs text-red-600">{{ fieldErrors.revenue_adjustment }}</p>
          </div>
        </div>

        <div>
          <label class="label">Target Revenue</label>
          <input v-model.number="form.target_revenue" type="number" min="0" class="input" placeholder="Rp..." />
          <p v-if="fieldErrors.target_revenue" class="mt-1 text-xs text-red-600">{{ fieldErrors.target_revenue }}</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
          <span class="font-medium text-slate-700">Ringkasan:</span> {{ preview }}
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="router.back()">Batal</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Menyimpan...' : 'Buat Scenario' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>
