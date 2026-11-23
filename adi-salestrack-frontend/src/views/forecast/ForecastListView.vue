<script setup lang="ts">
import { h, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { forecastsApi } from '@/api/forecasts'
import { usePagination } from '@/composables/usePagination'
import { useUiStore } from '@/stores/ui'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'
import { getApiErrorMessage } from '@/utils/form'
import { FORECAST_RUN_STATUSES, FORECAST_TYPES } from '@/constants'
import BaseBadge from '@/components/common/BaseBadge.vue'
import CrudTable from '@/components/common/CrudTable.vue'
import Pagination from '@/components/common/Pagination.vue'
import SearchInput from '@/components/common/SearchInput.vue'
import type { ColumnDef } from '@/components/common/CrudTable.vue'

const router = useRouter()
const ui = useUiStore()
const auth = useAuthStore()
const { formatDate, formatRupiah } = useFormat()

const filters = ref<Record<string, unknown>>({})
const page = usePagination((params) => forecastsApi.list({ ...filters.value, ...params }))

const columns: ColumnDef[] = [
  {
    key: 'name',
    label: 'Nama Forecast',
    render: (r) => h('span', { class: 'font-medium text-slate-800' }, (r.forecast_name as string) || (r.name as string)),
  },
  { key: 'forecast_type', label: 'Tipe', render: (r) => h(BaseBadge, { value: r.forecast_type as string }) },
  {
    key: 'period',
    label: 'Periode Forecast',
    render: (r) => `${(r.forecast_start_date as string) ?? (r.period_start as string) ?? '-'} → ${(r.forecast_end_date as string) ?? (r.period_end as string) ?? '-'}`,
  },
  {
    key: 'predicted_revenue',
    label: 'Expected Revenue',
    render: (r) => formatRupiah(r.predicted_revenue as number | null),
  },
  {
    key: 'simulation_count',
    label: 'Simulasi',
    render: (r) => String((r.simulation_count as number) ?? 0).replace(/\B(?=(\d{3})+(?!\d))/g, '.'),
  },
  { key: 'status', label: 'Status', render: (r) => h(BaseBadge, { value: r.status as string }) },
  { key: 'created_at', label: 'Dibuat', render: (r) => formatDate(r.created_at as string, true) },
]

function openDetail(row: Record<string, unknown>) {
  router.push({ name: 'forecast-detail', params: { id: Number(row.id) } })
}

async function remove(row: Record<string, unknown>) {
  const ok = await ui.askConfirmation({ message: `Yakin ingin menghapus forecast "${(row.forecast_name as string) || (row.name as string)}"?` })
  if (!ok) return
  try {
    await forecastsApi.remove(row.id as number)
    ui.notify('success', 'Forecast run dihapus')
    page.load()
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
  }
}

watch(
  filters,
  () => {
    if (page.currentPage !== 1) {
      page.currentPage = 1
    } else {
      page.load()
    }
  },
  { deep: true },
)

onMounted(() => page.load())
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-end gap-3">
      <SearchInput v-model="page.search" class="w-full max-w-xs" placeholder="Cari nama run..." @search="page.load()" />
      <div>
        <label class="label">Tipe</label>
        <select v-model="filters.forecast_type" class="input !w-44">
          <option :value="undefined">Semua Tipe</option>
          <option v-for="t in FORECAST_TYPES" :key="t" :value="t">{{ t }}</option>
        </select>
      </div>
      <div>
        <label class="label">Status</label>
        <select v-model="filters.status" class="input !w-44">
          <option :value="undefined">Semua Status</option>
          <option v-for="s in FORECAST_RUN_STATUSES" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>
      <button v-if="auth.isAdmin || auth.isSalesManager" class="btn-primary" @click="router.push('/forecasts/new')">
        + Buat Forecast Run
      </button>
    </div>

    <div class="card overflow-hidden">
      <CrudTable
        :columns="columns"
        :rows="page.items as unknown[]"
        :loading="page.loading"
        action-label="Buat Forecast Run"
        :show-edit="false"
        :show-delete="auth.isAdmin || auth.isSalesManager"
        @action="router.push('/forecasts/new')"
        @view="openDetail"
        @delete="remove"
      />
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
