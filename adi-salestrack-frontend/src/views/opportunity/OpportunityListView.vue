<script setup lang="ts">
import { h, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { opportunitiesApi } from '@/api/opportunities'
import { companiesApi, type Company } from '@/api/companies'
import { usersApi, type User } from '@/api/users'
import { principalsApi, type Principal } from '@/api/principals'
import { productCategoriesApi, type ProductCategory } from '@/api/productCategories'
import { usePagination } from '@/composables/usePagination'
import { useUiStore } from '@/stores/ui'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'
import { getApiErrorMessage } from '@/utils/form'
import { OPPORTUNITY_STAGES, OPPORTUNITY_STATUSES, AVAILABLE_YEARS } from '@/constants'
import BaseBadge from '@/components/common/BaseBadge.vue'
import CrudTable from '@/components/common/CrudTable.vue'
import Pagination from '@/components/common/Pagination.vue'
import SearchInput from '@/components/common/SearchInput.vue'
import type { ColumnDef } from '@/components/common/CrudTable.vue'

const router = useRouter()
const ui = useUiStore()
const auth = useAuthStore()
const { formatRupiah, formatPercent, formatDate } = useFormat()

const filters = ref<Record<string, unknown>>({
  stage: undefined,
  status: undefined,
  sales_id: undefined,
  principal_id: undefined,
  year: undefined,
  category_id: undefined,
})

const page = usePagination((params) => opportunitiesApi.list({ ...filters.value, ...params }))

const companies = ref<Company[]>([])
const users = ref<User[]>([])
const principals = ref<Principal[]>([])
const categories = ref<ProductCategory[]>([])

const columns: ColumnDef[] = [
  {
    key: 'title',
    label: 'Opportunity',
    render: (r) =>
      h(
        'div',
        { class: 'flex flex-col' },
        [
          h('span', { class: 'font-medium text-slate-800' }, (r.title as string) || (r.project_name as string) || '-'),
          h('span', { class: 'text-xs text-slate-500' }, (r.company as { name?: string })?.name || (r.client_name as string) || '-'),
        ],
      ),
  },
  {
    key: 'principal',
    label: 'Principal',
    render: (r) => (r.principal as { name?: string })?.name || '-',
  },
  { key: 'stage', label: 'Stage', render: (r) => h(BaseBadge, { value: (r.stage as string) || (r.progress as string) || '-' }) },
  {
    key: 'probability',
    label: 'Prob.',
    render: (r) => formatPercent(r.probability as number, 0),
  },
  { key: 'value', label: 'Nilai', align: 'right', render: (r) => formatRupiah((r.value as number) || (r.estimated_value as number) || 0) },
  {
    key: 'sales',
    label: 'Sales',
    render: (r) => (r.sales as { name?: string })?.name || (r.user as { name?: string })?.name || '-',
  },
  {
    key: 'expected_close_date',
    label: 'Closing Date',
    render: (r) => formatDate((r.closing_date as string) || (r.expected_close_date as string)),
  },
  { key: 'status', label: 'Status', render: (r) => h(BaseBadge, { value: r.status as string }) },
]

function openDetail(row: Record<string, unknown>) {
  router.push({ name: 'opportunity-detail', params: { id: Number(row.id) } })
}

function openEdit(row: Record<string, unknown>) {
  router.push({ name: 'opportunity-edit', params: { id: Number(row.id) } })
}

async function remove(row: Record<string, unknown>) {
  const ok = await ui.askConfirmation({
    message: `Yakin ingin menghapus opportunity "${row.title}"? Semua item dan aktivitas terkait akan dihapus.`,
  })
  if (!ok) return
  try {
    await opportunitiesApi.remove(row.id as number)
    ui.notify('success', 'Opportunity dihapus')
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

onMounted(async () => {
  page.load()
  companies.value = await companiesApi.list({ per_page: 100 }).then((r) => r.data).catch(() => [])
  if (auth.isAdmin || auth.isSalesManager || auth.isManagement) {
    users.value = await usersApi.list({ per_page: 100 }).then((r) => r.data).catch(() => [])
  }
  principals.value = await principalsApi.list({ per_page: 100 }).then((r) => r.data).catch(() => [])
  categories.value = await productCategoriesApi.list({ per_page: 100 }).then((r) => r.data).catch(() => [])
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-end gap-3">
      <SearchInput v-model="page.search" class="w-full max-w-xs" placeholder="Cari judul/klien..." @search="page.load()" />
      <div>
        <label class="label">Tahun</label>
        <select v-model="filters.year" class="input !w-36">
          <option :value="undefined">Semua Periode</option>
          <option v-for="y in AVAILABLE_YEARS" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
      <div>
        <label class="label">Stage</label>
        <select v-model="filters.stage" class="input !w-40">
          <option :value="undefined">Semua Stage</option>
          <option v-for="s in OPPORTUNITY_STAGES" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>
      <div>
        <label class="label">Status</label>
        <select v-model="filters.status" class="input !w-36">
          <option :value="undefined">Semua Status</option>
          <option v-for="s in OPPORTUNITY_STATUSES" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>
      <div v-if="auth.isAdmin || auth.isSalesManager || auth.isManagement">
        <label class="label">Sales</label>
        <select v-model="filters.sales_id" class="input !w-44">
          <option :value="undefined">Semua Sales</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
      <div>
        <label class="label">Principal</label>
        <select v-model="filters.principal_id" class="input !w-40">
          <option :value="undefined">Semua Principal</option>
          <option v-for="p in principals" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>
      <div>
        <label class="label">Kategori</label>
        <select v-model="filters.category_id" class="input !w-44">
          <option :value="undefined">Semua Kategori</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
      <button
        v-if="auth.isAdmin || auth.isSales || auth.isSalesManager"
        class="btn-primary ml-auto"
        @click="router.push('/opportunities/new')"
      >
        + Buat Opportunity
      </button>
    </div>

    <div class="card overflow-hidden">
      <CrudTable
        :columns="columns"
        :rows="page.items as unknown[]"
        :loading="page.loading"
        action-label="Buat Opportunity"
        :show-edit="auth.isAdmin || auth.isSales || auth.isSalesManager"
        :show-delete="auth.isAdmin || auth.isSales || auth.isSalesManager"
        @action="router.push('/opportunities/new')"
        @view="openDetail"
        @edit="openEdit"
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
