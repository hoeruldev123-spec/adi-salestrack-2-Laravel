<script setup lang="ts">
import { h, onMounted, reactive, ref } from 'vue'
import { productsApi, type Product } from '@/api/products'
import { principalsApi, type Principal } from '@/api/principals'
import { usePagination } from '@/composables/usePagination'
import { useUiStore } from '@/stores/ui'
import { useFormat } from '@/composables/useFormat'
import { getApiErrorMessage, getFieldError } from '@/utils/form'
import { ENTITY_STATUSES, PRODUCT_TYPES } from '@/constants'
import BaseBadge from '@/components/common/BaseBadge.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import CrudTable from '@/components/common/CrudTable.vue'
import Pagination from '@/components/common/Pagination.vue'
import SearchInput from '@/components/common/SearchInput.vue'
import type { ColumnDef } from '@/components/common/CrudTable.vue'

const ui = useUiStore()
const { formatRupiah, formatDate } = useFormat()

const page = usePagination((params) => productsApi.list(params))
const principals = ref<Principal[]>([])

const modalOpen = ref(false)
const editing = ref<Product | null>(null)
const saving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})

const form = reactive({
  name: '',
  principal_id: null as number | null,
  sku: '',
  type: 'PRODUCT',
  price: 0,
  status: 'ACTIVE',
})

const columns: ColumnDef[] = [
  { key: 'name', label: 'Nama', render: (r) => r.name },
  {
    key: 'principal',
    label: 'Principal',
    render: (r) => (r.principal as { name?: string } | null)?.name || (r.principal_id as string) || '-',
  },
  { key: 'sku', label: 'SKU', render: (r) => r.sku || '-' },
  { key: 'type', label: 'Tipe', render: (r) => h(BaseBadge, { value: r.type as string }) },
  { key: 'price', label: 'Harga', align: 'right', render: (r) => formatRupiah(r.price as number) },
  { key: 'status', label: 'Status', render: (r) => h(BaseBadge, { value: r.status as string }) },
  { key: 'created_at', label: 'Dibuat', render: (r) => formatDate(r.created_at as string) },
]

function openCreate() {
  editing.value = null
  Object.assign(form, { name: '', principal_id: null, sku: '', type: 'PRODUCT', price: 0, status: 'ACTIVE' })
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  modalOpen.value = true
}

function openEdit(row: Record<string, unknown>) {
  editing.value = row as unknown as Product
  Object.assign(form, {
    name: row.name ?? '',
    principal_id: (row.principal_id as number) ?? null,
    sku: row.sku ?? '',
    type: row.type ?? 'PRODUCT',
    price: Number(row.price ?? 0),
    status: row.status ?? 'ACTIVE',
  })
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  modalOpen.value = true
}

async function save() {
  saving.value = true
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  try {
    const payload = { ...form, principal_id: form.principal_id || null }
    if (editing.value) {
      await productsApi.update(editing.value.id, payload)
      ui.notify('success', 'Product berhasil diperbarui')
    } else {
      await productsApi.create(payload)
      ui.notify('success', 'Product berhasil dibuat')
    }
    modalOpen.value = false
    page.load()
  } catch (e) {
    for (const f of ['name', 'principal_id', 'sku', 'type', 'price', 'status']) {
      const msg = getFieldError(e, f)
      if (msg) fieldErrors[f] = msg
    }
    if (!Object.keys(fieldErrors).length) ui.notify('error', getApiErrorMessage(e))
  } finally {
    saving.value = false
  }
}

async function remove(row: Record<string, unknown>) {
  const ok = await ui.askConfirmation({ message: `Yakin ingin menghapus product "${row.name}"?` })
  if (!ok) return
  try {
    await productsApi.remove(row.id as number)
    ui.notify('success', 'Product dihapus')
    page.load()
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
  }
}

onMounted(async () => {
  page.load()
  principals.value = await principalsApi.list().then((r) => r.data)
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <SearchInput v-model="page.search" class="w-full max-w-xs" placeholder="Cari product..." @search="page.load()" />
      <button class="btn-primary" @click="openCreate">+ Tambah Product</button>
    </div>

    <div class="card overflow-hidden">
      <CrudTable
        :columns="columns"
        :rows="page.items as unknown[]"
        :loading="page.loading"
        action-label="Tambah Product"
        :show-view="false"
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

    <BaseModal :open="modalOpen" :title="editing ? 'Edit Product' : 'Tambah Product'" width="max-w-xl" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="label">Nama *</label>
          <input v-model="form.name" class="input" placeholder="Nama product/service" />
          <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">{{ fieldErrors.name }}</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Principal</label>
            <select v-model="form.principal_id" class="input">
              <option :value="null">Tanpa Principal</option>
              <option v-for="p in principals" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <p v-if="fieldErrors.principal_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.principal_id }}</p>
          </div>
          <div>
            <label class="label">SKU</label>
            <input v-model="form.sku" class="input" placeholder="SKU-001" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Tipe</label>
            <select v-model="form.type" class="input">
              <option v-for="t in PRODUCT_TYPES" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>
          <div>
            <label class="label">Harga</label>
            <input v-model.number="form.price" type="number" min="0" class="input" placeholder="0" />
            <p v-if="fieldErrors.price" class="mt-1 text-xs text-red-600">{{ fieldErrors.price }}</p>
          </div>
        </div>
        <div>
          <label class="label">Status</label>
          <select v-model="form.status" class="input">
            <option v-for="s in ENTITY_STATUSES" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="modalOpen = false">Batal</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
        </div>
      </form>
    </BaseModal>
  </div>
</template>
