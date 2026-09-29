<script setup lang="ts">
import { h, onMounted, reactive, ref } from 'vue'
import { companiesApi, type Company } from '@/api/companies'
import { usePagination } from '@/composables/usePagination'
import { useUiStore } from '@/stores/ui'
import { useFormat } from '@/composables/useFormat'
import { getApiErrorMessage, getFieldError } from '@/utils/form'
import { ENTITY_STATUSES } from '@/constants'
import BaseBadge from '@/components/common/BaseBadge.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import CrudTable from '@/components/common/CrudTable.vue'
import Pagination from '@/components/common/Pagination.vue'
import SearchInput from '@/components/common/SearchInput.vue'
import type { ColumnDef } from '@/components/common/CrudTable.vue'

const ui = useUiStore()
const { formatDate } = useFormat()

const page = usePagination((params) => companiesApi.list(params))

const modalOpen = ref(false)
const editing = ref<Company | null>(null)
const saving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})

const form = reactive({
  name: '',
  industry: '',
  email: '',
  phone: '',
  address: '',
  status: 'ACTIVE',
})

const columns: ColumnDef[] = [
  { key: 'name', label: 'Nama', render: (r) => r.name },
  { key: 'industry', label: 'Industri', render: (r) => r.industry || '-' },
  { key: 'email', label: 'Email', render: (r) => r.email || '-' },
  { key: 'phone', label: 'Telepon', render: (r) => r.phone || '-' },
  { key: 'status', label: 'Status', render: (r) => h(BaseBadge, { value: r.status as string }) },
  { key: 'created_at', label: 'Dibuat', render: (r) => formatDate(r.created_at as string) },
]

function openCreate() {
  editing.value = null
  Object.assign(form, { name: '', industry: '', email: '', phone: '', address: '', status: 'ACTIVE' })
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  modalOpen.value = true
}

function openEdit(row: Record<string, unknown>) {
  editing.value = row as unknown as Company
  Object.assign(form, {
    name: row.name ?? '',
    industry: row.industry ?? '',
    email: row.email ?? '',
    phone: row.phone ?? '',
    address: row.address ?? '',
    status: row.status ?? 'ACTIVE',
  })
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  modalOpen.value = true
}

async function save() {
  saving.value = true
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  try {
    if (editing.value) {
      await companiesApi.update(editing.value.id, { ...form })
      ui.notify('success', 'Company berhasil diperbarui')
    } else {
      await companiesApi.create({ ...form })
      ui.notify('success', 'Company berhasil dibuat')
    }
    modalOpen.value = false
    page.load()
  } catch (e) {
    for (const f of ['name', 'industry', 'email', 'phone', 'address', 'status']) {
      const msg = getFieldError(e, f)
      if (msg) fieldErrors[f] = msg
    }
    if (!Object.keys(fieldErrors).length) ui.notify('error', getApiErrorMessage(e))
  } finally {
    saving.value = false
  }
}

async function remove(row: Record<string, unknown>) {
  const ok = await ui.askConfirmation({ message: `Yakin ingin menghapus company "${row.name}"?` })
  if (!ok) return
  try {
    await companiesApi.remove(row.id as number)
    ui.notify('success', 'Company dihapus')
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
      <SearchInput v-model="page.search" class="w-full max-w-xs" placeholder="Cari company..." @search="page.load()" />
      <button class="btn-primary" @click="openCreate">+ Tambah Company</button>
    </div>

    <div class="card overflow-hidden">
      <CrudTable
        :columns="columns"
        :rows="page.items as unknown[]"
        :loading="page.loading"
        action-label="Tambah Company"
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

    <BaseModal :open="modalOpen" :title="editing ? 'Edit Company' : 'Tambah Company'" width="max-w-xl" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Nama *</label>
            <input v-model="form.name" class="input" placeholder="PT Contoh Sejahtera" />
            <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">{{ fieldErrors.name }}</p>
          </div>
          <div>
            <label class="label">Industri</label>
            <input v-model="form.industry" class="input" placeholder="Teknologi" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Email</label>
            <input v-model="form.email" type="email" class="input" placeholder="cs@perusahaan.com" />
            <p v-if="fieldErrors.email" class="mt-1 text-xs text-red-600">{{ fieldErrors.email }}</p>
          </div>
          <div>
            <label class="label">Telepon</label>
            <input v-model="form.phone" class="input" placeholder="+62 812 3456 7890" />
          </div>
        </div>
        <div>
          <label class="label">Alamat</label>
          <textarea v-model="form.address" class="input" rows="2" placeholder="Alamat lengkap" />
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
