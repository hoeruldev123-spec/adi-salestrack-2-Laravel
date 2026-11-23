<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { productCategoriesApi, type ProductCategory } from '@/api/productCategories'
import { usePagination } from '@/composables/usePagination'
import { useUiStore } from '@/stores/ui'
import { useFormat } from '@/composables/useFormat'
import { usePermissions } from '@/composables/usePermissions'
import { getApiErrorMessage, getFieldError } from '@/utils/form'
import BaseModal from '@/components/common/BaseModal.vue'
import CrudTable from '@/components/common/CrudTable.vue'
import Pagination from '@/components/common/Pagination.vue'
import SearchInput from '@/components/common/SearchInput.vue'
import type { ColumnDef } from '@/components/common/CrudTable.vue'

const ui = useUiStore()
const { formatDate } = useFormat()
const { canManageMaster } = usePermissions()

const page = usePagination((params) => productCategoriesApi.list(params))
const modalOpen = ref(false)
const editing = ref<ProductCategory | null>(null)
const saving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})

const form = reactive({ name: '', description: '' })

const columns: ColumnDef[] = [
  { key: 'name', label: 'Kategori', render: (r) => r.name },
  { key: 'description', label: 'Deskripsi', render: (r) => (r.description as string) || '-' },
  { key: 'products_count', label: 'Jumlah Produk', align: 'right', render: (r) => (r.products_count as number) ?? 0 },
  { key: 'created_at', label: 'Dibuat', render: (r) => formatDate(r.created_at as string) },
]

function openCreate() {
  editing.value = null
  Object.assign(form, { name: '', description: '' })
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  modalOpen.value = true
}

function openEdit(row: Record<string, unknown>) {
  editing.value = row as unknown as ProductCategory
  Object.assign(form, { name: row.name ?? '', description: row.description ?? '' })
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  modalOpen.value = true
}

async function save() {
  saving.value = true
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  try {
    if (editing.value) {
      await productCategoriesApi.update(editing.value.id, { ...form })
      ui.notify('success', 'Kategori berhasil diperbarui')
    } else {
      await productCategoriesApi.create({ ...form })
      ui.notify('success', 'Kategori berhasil dibuat')
    }
    modalOpen.value = false
    page.load()
  } catch (e) {
    for (const f of ['name', 'description']) {
      const msg = getFieldError(e, f)
      if (msg) fieldErrors[f] = msg
    }
    if (!Object.keys(fieldErrors).length) ui.notify('error', getApiErrorMessage(e))
  } finally {
    saving.value = false
  }
}

async function remove(row: Record<string, unknown>) {
  const ok = await ui.askConfirmation({ message: `Yakin ingin menghapus kategori "${row.name}"?` })
  if (!ok) return
  try {
    await productCategoriesApi.remove(row.id as number)
    ui.notify('success', 'Kategori dihapus')
    page.load()
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
  }
}

onMounted(() => page.load())
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <SearchInput v-model="page.search" class="w-full max-w-xs" placeholder="Cari kategori..." @search="page.load()" />
      <button v-if="canManageMaster" class="btn-primary" @click="openCreate">+ Tambah Kategori</button>
    </div>

    <div class="card overflow-hidden">
      <CrudTable
        :columns="columns"
        :rows="page.items as unknown[]"
        :loading="page.loading"
        action-label="Tambah Kategori"
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

    <BaseModal :open="modalOpen" :title="editing ? 'Edit Kategori' : 'Tambah Kategori'" width="max-w-lg" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="label">Nama *</label>
          <input v-model="form.name" class="input" placeholder="Data Analytics" />
          <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">{{ fieldErrors.name }}</p>
        </div>
        <div>
          <label class="label">Deskripsi</label>
          <textarea v-model="form.description" class="input" rows="3" placeholder="Deskripsi kategori" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="modalOpen = false">Batal</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
        </div>
      </form>
    </BaseModal>
  </div>
</template>
