<script setup lang="ts">
import { h, onMounted, ref, watch } from 'vue'
import { productsApi, type Product } from '@/api/products'
import { principalsApi, type Principal } from '@/api/principals'
import { productCategoriesApi, type ProductCategory } from '@/api/productCategories'
import { usePagination } from '@/composables/usePagination'
import { useUiStore } from '@/stores/ui'
import { useFormat } from '@/composables/useFormat'
import { getApiErrorMessage } from '@/utils/form'
import { PRODUCT_TYPES } from '@/constants'
import BaseBadge from '@/components/common/BaseBadge.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import CrudTable from '@/components/common/CrudTable.vue'
import Pagination from '@/components/common/Pagination.vue'
import SearchInput from '@/components/common/SearchInput.vue'
import type { ColumnDef } from '@/components/common/CrudTable.vue'

const ui = useUiStore()
const { formatRupiah } = useFormat()

const filters = ref<Record<string, unknown>>({})
const page = usePagination((params) => productsApi.list({ ...filters.value, ...params }))
const principals = ref<Principal[]>([])
const categories = ref<ProductCategory[]>([])

const detailOpen = ref(false)
const detailLoading = ref(false)
const detail = ref<Product | null>(null)

const columns: ColumnDef[] = [
  {
    key: 'name',
    label: 'Produk',
    render: (r) =>
      h('div', { class: 'flex flex-col' }, [
        h('span', { class: 'font-medium text-slate-800' }, r.name as string),
        h('span', { class: 'text-xs text-slate-500' }, (r.principal as { name?: string })?.name || '-'),
      ]),
  },
  {
    key: 'category',
    label: 'Kategori',
    render: (r) => (r.category as { name?: string } | null)?.name || '-',
  },
  {
    key: 'product_type',
    label: 'Tipe',
    render: (r) => h(BaseBadge, { value: (r.product_type || r.type) as string }),
  },
  { key: 'unit', label: 'Satuan', render: (r) => (r.unit as string) || '-' },
  { key: 'price', label: 'Harga Acuan', align: 'right', render: (r) => formatRupiah(r.price as number) },
  {
    key: 'price_lists_count',
    label: 'Price List',
    align: 'right',
    render: (r) => (r.price_lists_count as number) ?? 0,
  },
  {
    key: 'specifications_count',
    label: 'Spesifikasi',
    align: 'right',
    render: (r) => (r.specifications_count as number) ?? 0,
  },
]

async function openDetail(row: Record<string, unknown>) {
  detailOpen.value = true
  detailLoading.value = true
  detail.value = null
  try {
    detail.value = await productsApi.get(row.id as number)
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
    detailOpen.value = false
  } finally {
    detailLoading.value = false
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
  principals.value = await principalsApi.list({ per_page: 100 }).then((r) => r.data).catch(() => [])
  categories.value = await productCategoriesApi.list({ per_page: 100 }).then((r) => r.data).catch(() => [])
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-end gap-3">
      <SearchInput v-model="page.search" class="w-full max-w-xs" placeholder="Cari produk / SKU..." @search="page.load()" />
      <div>
        <label class="label">Principal</label>
        <select v-model="filters.principal_id" class="input !w-44">
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
      <div>
        <label class="label">Tipe Produk</label>
        <select v-model="filters.product_type" class="input !w-40">
          <option :value="undefined">Semua Tipe</option>
          <option v-for="t in PRODUCT_TYPES" :key="t" :value="t">{{ t }}</option>
        </select>
      </div>
    </div>

    <div class="card overflow-hidden">
      <CrudTable
        :columns="columns"
        :rows="page.items as unknown[]"
        :loading="page.loading"
        :show-view="true"
        :show-edit="false"
        :show-delete="false"
        empty-title="Katalog masih kosong"
        empty-subtitle="Belum ada produk yang terdaftar"
        @view="openDetail"
      />
      <Pagination
        :current-page="page.currentPage"
        :last-page="page.lastPage"
        :total="page.total"
        :pages="page.pages"
        @change="page.go"
      />
    </div>

    <BaseModal :open="detailOpen" title="Detail Produk" width="max-w-3xl" @close="detailOpen = false">
      <div v-if="detailLoading" class="py-10 text-center text-sm text-slate-500">Memuat detail...</div>
      <div v-else-if="detail" class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h4 class="text-lg font-semibold text-slate-900">{{ detail.name }}</h4>
            <p class="text-sm text-slate-500">
              {{ detail.principal?.name || '-' }} · {{ detail.category?.name || 'Tanpa kategori' }}
            </p>
          </div>
          <div class="flex items-center gap-2">
            <BaseBadge :value="detail.product_type || detail.type" />
            <BaseBadge :value="detail.is_active === false ? 'INACTIVE' : 'ACTIVE'" />
          </div>
        </div>

        <p v-if="detail.description" class="text-sm text-slate-600">{{ detail.description }}</p>

        <div class="grid grid-cols-2 gap-4 rounded-lg bg-slate-50 p-4 text-sm">
          <div>
            <p class="text-xs text-slate-500">SKU</p>
            <p class="font-medium text-slate-800">{{ detail.sku || '-' }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-500">Satuan</p>
            <p class="font-medium text-slate-800">{{ detail.unit || '-' }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-500">Harga Acuan</p>
            <p class="font-medium text-slate-800">{{ formatRupiah(detail.price) }}</p>
          </div>
        </div>

        <div>
          <h5 class="mb-2 text-sm font-semibold text-slate-800">Spesifikasi Teknis</h5>
          <div v-if="(detail.specifications ?? []).length === 0" class="rounded-lg border border-dashed border-slate-200 p-4 text-center text-sm text-slate-500">
            Belum ada spesifikasi
          </div>
          <div v-else class="overflow-hidden rounded-lg border border-slate-200">
            <table class="table-base">
              <thead>
                <tr><th>Spesifikasi</th><th>Nilai</th><th>Satuan</th></tr>
              </thead>
              <tbody>
                <tr v-for="spec in detail.specifications" :key="spec.id">
                  <td>{{ spec.specification_name }}</td>
                  <td>{{ spec.specification_value }}</td>
                  <td>{{ spec.unit || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div>
          <h5 class="mb-2 text-sm font-semibold text-slate-800">Price List</h5>
          <div v-if="(detail.price_lists ?? []).length === 0" class="rounded-lg border border-dashed border-slate-200 p-4 text-center text-sm text-slate-500">
            Belum ada price list
          </div>
          <div v-else class="overflow-hidden rounded-lg border border-slate-200">
            <table class="table-base">
              <thead>
                <tr><th>Tipe</th><th>Harga</th><th>Periode</th><th>Berlaku</th></tr>
              </thead>
              <tbody>
                <tr v-for="price in detail.price_lists" :key="price.id">
                  <td><BaseBadge :value="price.pricing_type" /></td>
                  <td>{{ price.currency }} {{ formatRupiah(price.unit_price) }}</td>
                  <td>{{ price.billing_period }}</td>
                  <td>{{ price.effective_date || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
