<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { forecastsApi, type RecommendationSummary } from '@/api/forecasts'
import { usePagination } from '@/composables/usePagination'
import { useFormat } from '@/composables/useFormat'
import BaseBadge from '@/components/common/BaseBadge.vue'
import Pagination from '@/components/common/Pagination.vue'
import SearchInput from '@/components/common/SearchInput.vue'

const route = useRoute()
const { formatRupiah, formatDate, formatPercent } = useFormat()

const filters = ref<Record<string, unknown>>({
  priority: undefined,
  recommendation_type: undefined,
  forecast_run_id: route.query.forecast_run_id ? Number(route.query.forecast_run_id) : undefined,
})

const summary = ref<RecommendationSummary>({ TOTAL: 0, HIGH: 0, MEDIUM: 0, LOW: 0 })

const page = usePagination(async (params) => {
  const res = await forecastsApi.allRecommendations({ ...filters.value, ...params })
  summary.value = res.summary
  return { data: res.data, meta: res.meta }
})

const priorities = ['HIGH', 'MEDIUM', 'LOW']
const types = [
  'HIGH_POTENTIAL',
  'HIGH_VALUE_LOW_PROBABILITY',
  'CLOSING_SOON',
  'INACTIVE_OPPORTUNITY',
  'LOW_POTENTIAL',
]

watch(() => route.query.forecast_run_id, (value) => {
  filters.value.forecast_run_id = value ? Number(value) : undefined
})

watch(filters, () => {
  if (page.currentPage !== 1) {
    page.currentPage = 1
  } else {
    page.load()
  }
}, { deep: true })

onMounted(() => page.load())
</script>

<template>
  <div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="card p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total Rekomendasi</p>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ summary.TOTAL }}</p>
      </div>
      <div class="card p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-red-500">Prioritas High</p>
        <p class="mt-2 text-2xl font-bold text-red-600">{{ summary.HIGH }}</p>
      </div>
      <div class="card p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-amber-500">Prioritas Medium</p>
        <p class="mt-2 text-2xl font-bold text-amber-600">{{ summary.MEDIUM }}</p>
      </div>
      <div class="card p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Prioritas Low</p>
        <p class="mt-2 text-2xl font-bold text-slate-700">{{ summary.LOW }}</p>
      </div>
    </div>

    <div class="flex flex-wrap items-end gap-3">
      <SearchInput v-model="page.search" class="w-full max-w-xs" placeholder="Cari opportunity..." @search="page.load()" />
      <div>
        <label class="label">Prioritas</label>
        <select v-model="filters.priority" class="input !w-40">
          <option :value="undefined">Semua</option>
          <option v-for="p in priorities" :key="p" :value="p">{{ p }}</option>
        </select>
      </div>
      <div>
        <label class="label">Jenis Rekomendasi</label>
        <select v-model="filters.recommendation_type" class="input !w-64">
          <option :value="undefined">Semua</option>
          <option v-for="t in types" :key="t" :value="t">{{ t.replaceAll('_', ' ') }}</option>
        </select>
      </div>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table-base">
          <thead>
            <tr>
              <th>Prioritas</th>
              <th>Opportunity</th>
              <th>Rekomendasi</th>
              <th class="text-right">Nilai</th>
              <th class="text-right">Probabilitas</th>
              <th>Closing Date</th>
              <th>Aktivitas Terakhir</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="page.loading">
              <td colspan="7" class="py-10 text-center text-sm text-slate-500">Memuat rekomendasi...</td>
            </tr>
            <tr v-else-if="page.error">
              <td colspan="7" class="py-10 text-center text-sm text-red-600">{{ page.error }}</td>
            </tr>
            <template v-else>
              <tr v-for="rec in page.items" :key="rec.id">
                <td><BaseBadge :value="rec.priority" /></td>
                <td>
                  <p class="font-medium text-slate-800">{{ rec.opportunity?.title || '-' }}</p>
                  <p class="text-xs text-slate-500">
                    {{ rec.opportunity?.client_name || '-' }} ·
                    {{ rec.opportunity?.principal?.name || 'Tanpa Principal' }}
                  </p>
                </td>
                <td class="max-w-md">
                  <p class="text-sm font-medium text-slate-800">{{ rec.recommendation_title }}</p>
                  <p class="mt-0.5 text-xs text-slate-500">{{ rec.action_suggestion }}</p>
                </td>
                <td class="text-right font-medium">{{ formatRupiah(rec.estimated_value) }}</td>
                <td class="text-right">
                  {{ rec.probability != null ? formatPercent(rec.probability, 1) : '-' }}
                </td>
                <td>{{ formatDate(rec.opportunity?.closing_date) }}</td>
                <td>{{ formatDate(rec.opportunity?.last_activity_at) }}</td>
              </tr>
              <tr v-if="!page.items.length">
                <td colspan="7" class="py-10 text-center text-sm text-slate-500">
                  Belum ada rekomendasi untuk filter ini.
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <Pagination
        :current-page="page.currentPage"
        :last-page="page.lastPage"
        :total="page.total"
        :pages="page.pages"
        @change="page.go"
      />
    </div>
  </div>
</template>
