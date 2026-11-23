<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { productsApi, type Product, type ProductSpecification } from '@/api/products'
import { useUiStore } from '@/stores/ui'
import { usePermissions } from '@/composables/usePermissions'
import { getApiErrorMessage, getFieldError } from '@/utils/form'
import BaseModal from '@/components/common/BaseModal.vue'
import CrudTable from '@/components/common/CrudTable.vue'
import SearchInput from '@/components/common/SearchInput.vue'
import type { ColumnDef } from '@/components/common/CrudTable.vue'

const ui = useUiStore()
const { canManageMaster } = usePermissions()

const products = ref<Product[]>([])
const selectedProductId = ref<number | null>(null)
const specs = ref<ProductSpecification[]>([])
const loading = ref(false)
const search = ref('')

const modalOpen = ref(false)
const editing = ref<ProductSpecification | null>(null)
const saving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive({ specification_name: '', specification_value: '', unit: '' })

const columns: ColumnDef[] = [
  { key: 'specification_name', label: 'Spesifikasi', render: (r) => r.specification_name },
  { key: 'specification_value', label: 'Nilai', render: (r) => r.specification_value },
  { key: 'unit', label: 'Satuan', render: (r) => (r.unit as string) || '-' },
]

async function loadSpecs() {
  if (!selectedProductId.value) {
    specs.value = []
    return
  }
  loading.value = true
  try {
    const res = await productsApi.specifications(selectedProductId.value, {
      per_page: 100,
      search: search.value || undefined,
    })
    specs.value = res.data
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
  } finally {
    loading.value = false
  }
}

function openCreate() {
  if (!selectedProductId.value) {
    ui.notify('error', 'Pilih produk terlebih dahulu')
    return
  }
  editing.value = null
  Object.assign(form, { specification_name: '', specification_value: '', unit: '' })
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  modalOpen.value = true
}

function openEdit(row: Record<string, unknown>) {
  editing.value = row as unknown as ProductSpecification
  Object.assign(form, {
    specification_name: row.specification_name ?? '',
    specification_value: row.specification_value ?? '',
    unit: row.unit ?? '',
  })
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  modalOpen.value = true
}

async function save() {
  if (!selectedProductId.value) return
  saving.value = true
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  try {
    if (editing.value) {
      await productsApi.updateSpecification(editing.value.id, { ...form })
      ui.notify('success', 'Spesifikasi berhasil diperbarui')
    } else {
      await productsApi.createSpecification(selectedProductId.value, { ...form })
      ui.notify('success', 'Spesifikasi berhasil ditambahkan')
    }
    modalOpen.value = false
    await loadSpecs()
  } catch (e) {
    for (const f of ['specification_name', 'specification_value', 'unit']) {
      const msg = getFieldError(e, f)
      if (msg) fieldErrors[f] = msg
    }
    if (!Object.keys(fieldErrors).length) ui.notify('error', getApiErrorMessage(e))
  } finally {
    saving.value = false
  }
}

async function remove(row: Record<string, unknown>) {
  const ok = await ui.askConfirmation({ message: `Hapus spesifikasi "${row.specification_name}"?` })
  if (!ok) return
  try {
    await productsApi.removeSpecification(row.id as number)
    ui.notify('success', 'Spesifikasi dihapus')
    await loadSpecs()
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
  }
}

watch(selectedProductId, loadSpecs)

onMounted(async () => {
  products.value = await productsApi.list({ per_page: 100 }).then((r) => r.data).catch(() => [])
  selectedProductId.value = products.value[0]?.id ?? null
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-end gap-3">
      <div>
        <label class="label">Produk</label>
        <select v-model.number="selectedProductId" class="input !w-72">
          <option :value="null">Pilih produk</option>
          <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>
      <SearchInput v-model="search" class="w-full max-w-xs" placeholder="Cari spesifikasi..." @search="loadSpecs" />
      <button v-if="canManageMaster" class="btn-primary" @click="openCreate">+ Tambah Spesifikasi</button>
    </div>

    <div class="card overflow-hidden">
      <CrudTable
        :columns="columns"
        :rows="specs as unknown[]"
        :loading="loading"
        action-label="Tambah Spesifikasi"
        :show-view="false"
        :show-edit="canManageMaster"
        :show-delete="canManageMaster"
        empty-title="Belum ada spesifikasi"
        empty-subtitle="Pilih produk lalu tambahkan spesifikasi teknis"
        @action="openCreate"
        @edit="openEdit"
        @delete="remove"
      />
    </div>

    <BaseModal :open="modalOpen" :title="editing ? 'Edit Spesifikasi' : 'Tambah Spesifikasi'" width="max-w-lg" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="label">Nama Spesifikasi *</label>
          <input v-model="form.specification_name" class="input" placeholder="vCPU / RAM / Storage" />
          <p v-if="fieldErrors.specification_name" class="mt-1 text-xs text-red-600">{{ fieldErrors.specification_name }}</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Nilai *</label>
            <input v-model="form.specification_value" class="input" placeholder="4" />
            <p v-if="fieldErrors.specification_value" class="mt-1 text-xs text-red-600">{{ fieldErrors.specification_value }}</p>
          </div>
          <div>
            <label class="label">Satuan</label>
            <input v-model="form.unit" class="input" placeholder="Core / GB / TB" />
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="modalOpen = false">Batal</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
        </div>
      </form>
    </BaseModal>
  </div>
</template>
