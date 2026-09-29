<script setup lang="ts">
import { h, onMounted, reactive, ref } from 'vue'
import { principalsApi, type Principal } from '@/api/principals'
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

const page = usePagination((params) => principalsApi.list(params))

const modalOpen = ref(false)
const editing = ref<Principal | null>(null)
const saving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})

const form = reactive({
  name: '',
  description: '',
  website: '',
  status: 'ACTIVE',
})

const columns: ColumnDef[] = [
  { key: 'name', label: 'Nama', render: (r) => r.name },
  { key: 'description', label: 'Deskripsi', render: (r) => (r.description as string) || '-' },
  {
    key: 'website',
    label: 'Website',
    render: (r) =>
      r.website
        ? h('a', { href: r.website as string, target: '_blank', class: 'text-primary-600 hover:underline' }, r.website as string)
        : '-',
  },
  { key: 'status', label: 'Status', render: (r) => h(BaseBadge, { value: r.status as string }) },
  { key: 'created_at', label: 'Dibuat', render: (r) => formatDate(r.created_at as string) },
]

function openCreate() {
  editing.value = null
  Object.assign(form, { name: '', description: '', website: '', status: 'ACTIVE' })
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  modalOpen.value = true
}

function openEdit(row: Record<string, unknown>) {
  editing.value = row as unknown as Principal
  Object.assign(form, {
    name: row.name ?? '',
    description: row.description ?? '',
    website: row.website ?? '',
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
      await principalsApi.update(editing.value.id, { ...form })
      ui.notify('success', 'Principal berhasil diperbarui')
    } else {
      await principalsApi.create({ ...form })
      ui.notify('success', 'Principal berhasil dibuat')
    }
    modalOpen.value = false
    page.load()
  } catch (e) {
    for (const f of ['name', 'description', 'website', 'status']) {
      const msg = getFieldError(e, f)
      if (msg) fieldErrors[f] = msg
    }
    if (!Object.keys(fieldErrors).length) ui.notify('error', getApiErrorMessage(e))
  } finally {
    saving.value = false
  }
}

async function remove(row: Record<string, unknown>) {
  const ok = await ui.askConfirmation({ message: `Yakin ingin menghapus principal "${row.name}"?` })
  if (!ok) return
  try {
    await principalsApi.remove(row.id as number)
    ui.notify('success', 'Principal dihapus')
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
      <SearchInput v-model="page.search" class="w-full max-w-xs" placeholder="Cari principal..." @search="page.load()" />
      <button class="btn-primary" @click="openCreate">+ Tambah Principal</button>
    </div>

    <div class="card overflow-hidden">
      <CrudTable
        :columns="columns"
        :rows="page.items as unknown[]"
        :loading="page.loading"
        action-label="Tambah Principal"
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

    <BaseModal :open="modalOpen" :title="editing ? 'Edit Principal' : 'Tambah Principal'" width="max-w-xl" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="label">Nama *</label>
          <input v-model="form.name" class="input" placeholder="PT Principal Induk" />
          <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">{{ fieldErrors.name }}</p>
        </div>
        <div>
          <label class="label">Deskripsi</label>
          <textarea v-model="form.description" class="input" rows="2" placeholder="Deskripsi principal" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Website</label>
            <input v-model="form.website" class="input" placeholder="https://..." />
            <p v-if="fieldErrors.website" class="mt-1 text-xs text-red-600">{{ fieldErrors.website }}</p>
          </div>
          <div>
            <label class="label">Status</label>
            <select v-model="form.status" class="input">
              <option v-for="s in ENTITY_STATUSES" :key="s" :value="s">{{ s }}</option>
            </select>
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
