<script setup lang="ts">
import { h, onMounted, reactive, ref } from 'vue'
import { priceListsApi, type PriceList } from '@/api/priceLists'
import { productsApi, type Product } from '@/api/products'
import { usePagination } from '@/composables/usePagination'
import { useUiStore } from '@/stores/ui'
import { useFormat } from '@/composables/useFormat'
import { usePermissions } from '@/composables/usePermissions'
import { getApiErrorMessage, getFieldError } from '@/utils/form'
import { BILLING_PERIODS, PRICING_TYPES } from '@/constants'
import BaseBadge from '@/components/common/BaseBadge.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import CrudTable from '@/components/common/CrudTable.vue'
import Pagination from '@/components/common/Pagination.vue'
import SearchInput from '@/components/common/SearchInput.vue'
import type { ColumnDef } from '@/components/common/CrudTable.vue'

const ui = useUiStore()
const { formatRupiah, formatDate } = useFormat()
const { canManageMaster } = usePermissions()

const page = usePagination((params) => priceListsApi.list(params))
const products = ref<Product[]>([])

const modalOpen = ref(false)
const editing = ref<PriceList | null>(null)
const saving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})

const form = reactive({
  product_id: null as number | null,
  pricing_type: 'LICENSE',
  unit_price: 0,
  currency: 'IDR',
  billing_period: 'YEARLY',
  effective_date: '',
  expiry_date: '',
  is_active: true,
})

const columns: ColumnDef[] = [
  {
    key: 'product',
    label: 'Produk',
    render: (r) => (r.product as { name?: string } | null)?.name || '-',
  },
  { key: 'pricing_type', label: 'Tipe Harga', render: (r) => h(BaseBadge, { value: r.pricing_type as string }) },
  { key: 'unit_price', label: 'Harga', align: 'right', render: (r) => formatRupiah(r.unit_price as number) },
  { key: 'currency', label: 'Mata Uang', render: (r) => r.currency },
  { key: 'billing_period', label: 'Periode', render: (r) => h(BaseBadge, { value: r.billing_period as string }) },
  { key: 'effective_date', label: 'Berlaku', render: (r) => formatDate(r.effective_date as string) },
  {
    key: 'is_active',
    label: 'Status',
    render: (r) => h(BaseBadge, { value: r.is_active ? 'ACTIVE' : 'INACTIVE' }),
  },
]

function openCreate() {
  editing.value = null
  Object.assign(form, {
    product_id: products.value[0]?.id ?? null,
    pricing_type: 'LICENSE',
    unit_price: 0,
    currency: 'IDR',
    billing_period: 'YEARLY',
    effective_date: '',
    expiry_date: '',
    is_active: true,
  })
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  modalOpen.value = true
}

function openEdit(row: Record<string, unknown>) {
  editing.value = row as unknown as PriceList
  Object.assign(form, {
    product_id: (row.product_id as number) ?? null,
    pricing_type: row.pricing_type ?? 'LICENSE',
    unit_price: Number(row.unit_price ?? 0),
    currency: row.currency ?? 'IDR',
    billing_period: row.billing_period ?? 'YEARLY',
    effective_date: row.effective_date ?? '',
    expiry_date: row.expiry_date ?? '',
    is_active: Boolean(row.is_active),
  })
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  modalOpen.value = true
}

async function save() {
  saving.value = true
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  try {
    const payload = {
      ...form,
      product_id: form.product_id || null,
      effective_date: form.effective_date || null,
      expiry_date: form.expiry_date || null,
    }
    if (editing.value) {
      await priceListsApi.update(editing.value.id, payload)
      ui.notify('success', 'Price list berhasil diperbarui')
    } else {
      await priceListsApi.create(payload)
      ui.notify('success', 'Price list berhasil dibuat')
    }
    modalOpen.value = false
    page.load()
  } catch (e) {
    for (const f of ['product_id', 'pricing_type', 'unit_price', 'currency', 'billing_period', 'effective_date', 'expiry_date']) {
      const msg = getFieldError(e, f)
      if (msg) fieldErrors[f] = msg
    }
    if (!Object.keys(fieldErrors).length) ui.notify('error', getApiErrorMessage(e))
  } finally {
    saving.value = false
  }
}

async function remove(row: Record<string, unknown>) {
  const ok = await ui.askConfirmation({ message: 'Yakin ingin menghapus price list ini?' })
  if (!ok) return
  try {
    await priceListsApi.remove(row.id as number)
    ui.notify('success', 'Price list dihapus')
    page.load()
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
  }
}

onMounted(async () => {
  page.load()
  products.value = await productsApi.list({ per_page: 100 }).then((r) => r.data).catch(() => [])
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <SearchInput v-model="page.search" class="w-full max-w-xs" placeholder="Cari produk..." @search="page.load()" />
      <button v-if="canManageMaster" class="btn-primary" @click="openCreate">+ Tambah Price List</button>
    </div>

    <div class="card overflow-hidden">
      <CrudTable
        :columns="columns"
        :rows="page.items as unknown[]"
        :loading="page.loading"
        action-label="Tambah Price List"
        :show-view="false"
        :show-edit="canManageMaster"
        :show-delete="canManageMaster"
        @action="openCreate"
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

    <BaseModal :open="modalOpen" :title="editing ? 'Edit Price List' : 'Tambah Price List'" width="max-w-xl" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="label">Produk *</label>
          <select v-model="form.product_id" class="input">
            <option :value="null">Pilih produk</option>
            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <p v-if="fieldErrors.product_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.product_id }}</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Tipe Harga</label>
            <select v-model="form.pricing_type" class="input">
              <option v-for="t in PRICING_TYPES" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>
          <div>
            <label class="label">Periode Billing</label>
            <select v-model="form.billing_period" class="input">
              <option v-for="b in BILLING_PERIODS" :key="b" :value="b">{{ b }}</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <div class="col-span-2">
            <label class="label">Harga Satuan *</label>
            <input v-model.number="form.unit_price" type="number" min="0" class="input" />
            <p v-if="fieldErrors.unit_price" class="mt-1 text-xs text-red-600">{{ fieldErrors.unit_price }}</p>
          </div>
          <div>
            <label class="label">Mata Uang</label>
            <input v-model="form.currency" class="input" maxlength="3" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Berlaku Dari</label>
            <input v-model="form.effective_date" type="date" class="input" />
          </div>
          <div>
            <label class="label">Berlaku Sampai</label>
            <input v-model="form.expiry_date" type="date" class="input" />
            <p v-if="fieldErrors.expiry_date" class="mt-1 text-xs text-red-600">{{ fieldErrors.expiry_date }}</p>
          </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
          <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
          Harga aktif
        </label>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="modalOpen = false">Batal</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
        </div>
      </form>
    </BaseModal>
  </div>
</template>
