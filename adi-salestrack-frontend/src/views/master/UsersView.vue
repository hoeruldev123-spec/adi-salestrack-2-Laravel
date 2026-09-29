<script setup lang="ts">
import { h, onMounted, reactive, ref } from 'vue'
import { usersApi, type User } from '@/api/users'
import { rolesApi } from '@/api/roles'
import { usePagination } from '@/composables/usePagination'
import { useUiStore } from '@/stores/ui'
import { useFormat } from '@/composables/useFormat'
import { getApiErrorMessage, getFieldError } from '@/utils/form'
import { ENTITY_STATUSES, badgeColor, roleNameOf } from '@/constants'
import BaseBadge from '@/components/common/BaseBadge.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import CrudTable from '@/components/common/CrudTable.vue'
import Pagination from '@/components/common/Pagination.vue'
import SearchInput from '@/components/common/SearchInput.vue'
import type { ColumnDef } from '@/components/common/CrudTable.vue'

const ui = useUiStore()
const { formatDate } = useFormat()

const page = usePagination((params) => usersApi.list(params))

const roles = ref<Awaited<ReturnType<typeof rolesApi.list>>>([])

const modalOpen = ref(false)
const editing = ref<User | null>(null)
const saving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})

const form = reactive({
  name: '',
  email: '',
  role: 'SALES',
  status: 'ACTIVE',
  password: '',
})

const columns: ColumnDef[] = [
  { key: 'name', label: 'Nama', render: (r) => r.name },
  { key: 'email', label: 'Email', render: (r) => r.email },
  { key: 'role', label: 'Role', render: (r) => h(BaseBadge, { value: roleNameOf(r.role) }) },
  { key: 'status', label: 'Status', render: (r) => h(BaseBadge, { value: r.status as string }) },
  { key: 'created_at', label: 'Dibuat', render: (r) => formatDate(r.created_at as string, true) },
]

function roleIdOf(roleName: string): number {
  const found = roles.value.find((r) => r.name === roleName)
  if (found) return found.id
  const role = typeof editing.value?.role === 'object' ? editing.value.role : null
  return role?.id ?? 0
}

function openCreate() {
  editing.value = null
  Object.assign(form, { name: '', email: '', role: 'SALES', status: 'ACTIVE', password: '' })
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  modalOpen.value = true
}

function openEdit(row: Record<string, unknown>) {
  editing.value = row as unknown as User
  Object.assign(form, {
    name: row.name ?? '',
    email: row.email ?? '',
    role: roleNameOf(row.role) || 'SALES',
    status: row.status ?? 'ACTIVE',
    password: '',
  })
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  modalOpen.value = true
}

async function save() {
  saving.value = true
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  try {
    if (editing.value) {
      await usersApi.update(editing.value.id, {
        name: form.name,
        email: form.email,
        role_id: roleIdOf(form.role),
        status: form.status,
      })
      ui.notify('success', 'User berhasil diperbarui')
    } else {
      await usersApi.create({
        name: form.name,
        email: form.email,
        role_id: roleIdOf(form.role),
        status: form.status,
        password: form.password,
      })
      ui.notify('success', 'User berhasil dibuat')
    }
    modalOpen.value = false
    page.load()
  } catch (e) {
    for (const f of ['name', 'email', 'role_id', 'status', 'password']) {
      const msg = getFieldError(e, f)
      if (msg) fieldErrors[f] = msg
    }
    const msg = getApiErrorMessage(e)
    if (!Object.keys(fieldErrors).length) ui.notify('error', msg)
  } finally {
    saving.value = false
  }
}

async function remove(row: Record<string, unknown>) {
  const ok = await ui.askConfirmation({
    message: `Yakin ingin menghapus user "${row.name}"?`,
  })
  if (!ok) return
  try {
    await usersApi.remove(row.id as number)
    ui.notify('success', 'User dihapus')
    page.load()
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
  }
}

onMounted(() => {
  page.load()
  rolesApi.list().then((r) => (roles.value = r)).catch(() => {})
})
void badgeColor
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <SearchInput v-model="page.search" class="w-full max-w-xs" placeholder="Cari nama atau email..." @search="page.load()" />
      <button class="btn-primary" @click="openCreate">+ Tambah User</button>
    </div>

    <div class="card overflow-hidden">
      <CrudTable
        :columns="columns"
        :rows="page.items as unknown[]"
        :loading="page.loading"
        action-label="Tambah User"
        empty-subtitle="User akan muncul di sini setelah ditambahkan"
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

    <BaseModal :open="modalOpen" :title="editing ? 'Edit User' : 'Tambah User'" @close="modalOpen = false">
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="label">Nama</label>
          <input v-model="form.name" class="input" placeholder="Nama lengkap" />
          <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">{{ fieldErrors.name }}</p>
        </div>
        <div>
          <label class="label">Email</label>
          <input v-model="form.email" type="email" class="input" placeholder="nama@perusahaan.com" />
          <p v-if="fieldErrors.email" class="mt-1 text-xs text-red-600">{{ fieldErrors.email }}</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Role</label>
            <select v-model="form.role" class="input">
              <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</option>
            </select>
            <p v-if="fieldErrors.role_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.role_id }}</p>
          </div>
          <div>
            <label class="label">Status</label>
            <select v-model="form.status" class="input">
              <option v-for="s in ENTITY_STATUSES" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>
        </div>
        <div v-if="!editing">
          <label class="label">Password</label>
          <input v-model="form.password" type="password" class="input" placeholder="Password awal" />
          <p v-if="fieldErrors.password" class="mt-1 text-xs text-red-600">{{ fieldErrors.password }}</p>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="modalOpen = false">Batal</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
        </div>
      </form>
    </BaseModal>
  </div>
</template>
