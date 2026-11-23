<script setup lang="ts">
import { h, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { transactionsApi, type SalesTransaction } from '@/api/transactions'
import { usersApi, type User } from '@/api/users'
import { principalsApi, type Principal } from '@/api/principals'
import { usePagination } from '@/composables/usePagination'
import { useUiStore } from '@/stores/ui'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'
import { getApiErrorMessage } from '@/utils/form'
import { TRANSACTION_STATUSES, AVAILABLE_YEARS } from '@/constants'
import BaseBadge from '@/components/common/BaseBadge.vue'
import CrudTable from '@/components/common/CrudTable.vue'
import Pagination from '@/components/common/Pagination.vue'
import SearchInput from '@/components/common/SearchInput.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import type { ColumnDef } from '@/components/common/CrudTable.vue'

const router = useRouter()
const ui = useUiStore()
const auth = useAuthStore()
const { formatRupiah, formatDate } = useFormat()

const filters = ref<Record<string, unknown>>({
  year: undefined,
  status: undefined,
  sales_id: undefined,
  principal_id: undefined,
})

const page = usePagination((params) => transactionsApi.list({ ...filters.value, ...params }))

const users = ref<User[]>([])
const principals = ref<Principal[]>([])

const detailOpen = ref(false)
const detail = ref<SalesTransaction | null>(null)
const detailItems = ref<Awaited<ReturnType<typeof transactionsApi.items>>>([])

const columns: ColumnDef[] = [
  { key: 'transaction_number', label: 'Nomor', render: (r) => r.transaction_number || `#${r.id}` },
  {
    key: 'opportunity',
    label: 'Opportunity',
    render: (r) =>
      h(
        'div',
        { class: 'flex flex-col' },
        [
          h('span', { class: 'font-medium text-slate-800' }, (r.opportunity as { title?: string })?.title || '-'),
          h('span', { class: 'text-xs text-slate-500' }, (r.company as { name?: string })?.name || '-'),
        ],
      ),
  },
  {
    key: 'principal',
    label: 'Principal',
    render: (r) =>
      ((r.opportunity as { principal?: { name?: string } })?.principal?.name) || '-',
  },
  {
    key: 'sales',
    label: 'Sales',
    render: (r) =>
      (r.sales as { name?: string })?.name || (r.user as { name?: string })?.name || '-',
  },
  { key: 'transaction_date', label: 'Tanggal', render: (r) => formatDate(r.transaction_date as string) },
  { key: 'status', label: 'Status', render: (r) => h(BaseBadge, { value: r.status as string }) },
  { key: 'total_amount', label: 'Total', align: 'right', render: (r) => formatRupiah(r.total_amount as number) },
]

function openDetail(row: Record<string, unknown>) {
  detail.value = row as unknown as SalesTransaction
  detailOpen.value = true
  transactionsApi.items(Number(row.id)).then((items) => (detailItems.value = items))
}

async function remove(row: Record<string, unknown>) {
  const ok = await ui.askConfirmation({ message: `Yakin ingin menghapus transaction "${row.transaction_number ?? row.id}"?` })
  if (!ok) return
  try {
    await transactionsApi.remove(row.id as number)
    ui.notify('success', 'Transaction dihapus')
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
  if (auth.isAdmin || auth.isSalesManager || auth.isManagement) {
    users.value = await usersApi.list({ per_page: 100 }).then((r) => r.data).catch(() => [])
  }
  principals.value = await principalsApi.list({ per_page: 100 }).then((r) => r.data).catch(() => [])
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-end gap-3">
      <SearchInput v-model="page.search" class="w-full max-w-xs" placeholder="Cari nomor/opportunity/klien..." @search="page.load()" />
      <div>
        <label class="label">Tahun</label>
        <select v-model="filters.year" class="input !w-36">
          <option :value="undefined">Semua Periode</option>
          <option v-for="y in AVAILABLE_YEARS" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
      <div>
        <label class="label">Status</label>
        <select v-model="filters.status" class="input !w-40">
          <option :value="undefined">Semua Status</option>
          <option v-for="s in TRANSACTION_STATUSES" :key="s" :value="s">{{ s }}</option>
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
        <select v-model="filters.principal_id" class="input !w-44">
          <option :value="undefined">Semua Principal</option>
          <option v-for="p in principals" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>
      <button
        v-if="auth.isAdmin || auth.isSales || auth.isSalesManager"
        class="btn-primary ml-auto"
        @click="router.push('/transactions/new')"
      >
        + Buat Transaction
      </button>
    </div>

    <div class="card overflow-hidden">
      <CrudTable
        :columns="columns"
        :rows="page.items as unknown[]"
        :loading="page.loading"
        action-label="Buat Transaction"
        :show-edit="false"
        :show-delete="auth.isAdmin || auth.isSales || auth.isSalesManager"
        @action="router.push('/transactions/new')"
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

    <BaseModal :open="detailOpen" title="Detail Transaction" width="max-w-2xl" @close="detailOpen = false">
      <div v-if="detail" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <p class="text-xs text-slate-500">Nomor</p>
            <p class="text-sm font-medium text-slate-800">{{ detail.transaction_number || `#${detail.id}` }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-500">Status</p>
            <BaseBadge :value="detail.status" />
          </div>
          <div>
            <p class="text-xs text-slate-500">Opportunity</p>
            <p class="text-sm font-medium text-slate-800">{{ detail.opportunity?.title || '-' }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-500">Perusahaan / Klien</p>
            <p class="text-sm font-medium text-slate-800">{{ detail.company?.name || '-' }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-500">Sales</p>
            <p class="text-sm font-medium text-slate-800">{{ detail.sales?.name || detail.user?.name || '-' }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-500">Principal</p>
            <p class="text-sm font-medium text-slate-800">{{ detail.opportunity?.principal?.name || '-' }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-500">Tanggal</p>
            <p class="text-sm font-medium text-slate-800">{{ formatDate(detail.transaction_date) }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-500">Total</p>
            <p class="text-sm font-bold text-slate-800">{{ formatRupiah(detail.total_amount) }}</p>
          </div>
        </div>
        <div>
          <p class="label">Item</p>
          <table v-if="detailItems.length" class="table-base">
            <thead>
              <tr>
                <th>Produk</th>
                <th class="w-16">Qty</th>
                <th class="w-28 text-right">Harga</th>
                <th class="w-24 text-right">Diskon</th>
                <th class="w-28 text-right">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, i) in detailItems" :key="i">
                <td>{{ (item.product as { name?: string })?.name || item.name || '-' }}</td>
                <td>{{ item.quantity }}</td>
                <td class="text-right">{{ formatRupiah(item.unit_price) }}</td>
                <td class="text-right">{{ formatRupiah(item.discount) }}</td>
                <td class="text-right font-medium">{{ formatRupiah(item.subtotal) }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="py-4 text-center text-sm text-slate-500">Memuat item...</p>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
