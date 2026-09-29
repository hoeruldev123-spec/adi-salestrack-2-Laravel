<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { opportunitiesApi, type Opportunity } from '@/api/opportunities'
import { activitiesApi, type Activity } from '@/api/activities'
import { useUiStore } from '@/stores/ui'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'
import { getApiErrorMessage, getFieldError } from '@/utils/form'
import { ACTIVITY_STATUSES, ACTIVITY_TYPES, badgeColor } from '@/constants'
import BaseBadge from '@/components/common/BaseBadge.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import {
  Pencil,
  Trash2,
  Phone,
  Calendar,
  Mail,
  Repeat,
  StickyNote,
  ListChecks,
  Clock,
} from 'lucide-vue-next'
import type { ColumnDef } from '@/components/common/CrudTable.vue'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const auth = useAuthStore()
const { formatRupiah, formatPercent, formatDate } = useFormat()

const id = Number(route.params.id)
const opp = ref<Opportunity | null>(null)
const logs = ref<Awaited<ReturnType<typeof opportunitiesApi.logs>>>([])
const activities = ref<Activity[]>([])
const loading = ref(true)

const canEdit = computed(() => auth.isAdmin || auth.isSales || auth.isSalesManager)

const itemColumns: ColumnDef[] = [
  { key: 'product', label: 'Produk', render: (r) => (r.product as { name?: string })?.name || r.name || '-' },
  { key: 'quantity', label: 'Qty', align: 'right' },
  { key: 'unit_price', label: 'Unit Price', align: 'right', render: (r) => formatRupiah(r.unit_price as number) },
  { key: 'discount', label: 'Diskon', align: 'right', render: (r) => formatRupiah(r.discount as number) },
  { key: 'subtotal', label: 'Subtotal', align: 'right', render: (r) => formatRupiah(r.subtotal as number) },
]

const activityIcons: Record<string, typeof Phone> = {
  CALL: Phone,
  MEETING: Calendar,
  EMAIL: Mail,
  FOLLOW_UP: Repeat,
  NOTE: StickyNote,
  TASK: ListChecks,
}

async function load() {
  loading.value = true
  try {
    const [op, act, lg] = await Promise.all([
      opportunitiesApi.get(id),
      activitiesApi.list(id).then((r) => r.data),
      opportunitiesApi.logs(id),
    ])
    opp.value = op
    activities.value = act
    logs.value = lg
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
  } finally {
    loading.value = false
  }
}

async function remove() {
  const ok = await ui.askConfirmation({
    message: `Yakin ingin menghapus opportunity "${opp.value?.title}"?`,
  })
  if (!ok) return
  try {
    await opportunitiesApi.remove(id)
    ui.notify('success', 'Opportunity dihapus')
    router.push('/opportunities')
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
  }
}

// Activity CRUD
const activityModal = ref(false)
const editingActivity = ref<Activity | null>(null)
const savingActivity = ref(false)
const actErrors = reactive<Record<string, string>>({})
const actForm = reactive({
  type: 'CALL',
  subject: '',
  description: '',
  status: 'OPEN',
  scheduled_at: '',
})

function openCreateActivity() {
  editingActivity.value = null
  Object.assign(actForm, { type: 'CALL', subject: '', description: '', status: 'OPEN', scheduled_at: '' })
  Object.keys(actErrors).forEach((k) => delete actErrors[k])
  activityModal.value = true
}

function openEditActivity(a: Activity) {
  editingActivity.value = a
  Object.assign(actForm, {
    type: a.type,
    subject: a.subject ?? '',
    description: a.description ?? '',
    status: a.status,
    scheduled_at: a.scheduled_at ? String(a.scheduled_at).slice(0, 10) : '',
  })
  Object.keys(actErrors).forEach((k) => delete actErrors[k])
  activityModal.value = true
}

async function saveActivity() {
  savingActivity.value = true
  Object.keys(actErrors).forEach((k) => delete actErrors[k])
  try {
    const payload = {
      type: actForm.type,
      subject: actForm.subject,
      description: actForm.description,
      status: actForm.status,
      scheduled_at: actForm.scheduled_at || null,
    }
    if (editingActivity.value) {
      await activitiesApi.update(id, editingActivity.value.id, payload)
      ui.notify('success', 'Activity diperbarui')
    } else {
      await activitiesApi.create(id, payload)
      ui.notify('success', 'Activity dibuat')
    }
    activityModal.value = false
    activities.value = await activitiesApi.list(id).then((r) => r.data)
  } catch (e) {
    for (const f of ['type', 'subject', 'status']) {
      const msg = getFieldError(e, f)
      if (msg) actErrors[f] = msg
    }
    if (!Object.keys(actErrors).length) ui.notify('error', getApiErrorMessage(e))
  } finally {
    savingActivity.value = false
  }
}

async function removeActivity(a: Activity) {
  const ok = await ui.askConfirmation({ message: `Yakin ingin menghapus activity "${a.subject || a.type}"?` })
  if (!ok) return
  try {
    await activitiesApi.remove(id, a.id)
    ui.notify('success', 'Activity dihapus')
    activities.value = await activitiesApi.list(id).then((r) => r.data)
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
  }
}

onMounted(load)
</script>

<template>
  <div v-if="loading" class="space-y-4">
    <div class="card h-40 animate-pulse"></div>
    <div class="card h-64 animate-pulse"></div>
  </div>

  <div v-else-if="opp" class="space-y-4">
    <!-- Header -->
    <div class="card p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-lg font-semibold text-slate-900">{{ opp.title }}</h2>
            <BaseBadge :value="opp.stage" />
            <BaseBadge :value="opp.status" />
          </div>
          <p class="mt-1 text-sm text-slate-500">
            {{ opp.company?.name ?? 'Tanpa company' }} · Sales: {{ opp.sales?.name ?? '-' }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button v-if="canEdit" class="btn-secondary btn-sm" @click="router.push(`/opportunities/${id}/edit`)">
            <Pencil class="h-3.5 w-3.5" /> Edit
          </button>
          <button v-if="canEdit" class="btn-danger btn-sm" @click="remove">
            <Trash2 class="h-3.5 w-3.5" /> Hapus
          </button>
        </div>
      </div>
      <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div>
          <p class="text-xs text-slate-500">Nilai</p>
          <p class="text-base font-bold text-slate-900">{{ formatRupiah(opp.value) }}</p>
        </div>
        <div>
          <p class="text-xs text-slate-500">Probability</p>
          <p class="text-base font-bold text-slate-900">{{ formatPercent(opp.probability, 0) }}</p>
        </div>
        <div>
          <p class="text-xs text-slate-500">Expected Close</p>
          <p class="text-base font-semibold text-slate-800">{{ formatDate(opp.expected_close_date) }}</p>
        </div>
        <div>
          <p class="text-xs text-slate-500">Dibuat</p>
          <p class="text-base font-semibold text-slate-800">{{ formatDate(opp.created_at, true) }}</p>
        </div>
      </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <!-- Items -->
      <div class="card">
        <div class="card-header"><h3 class="text-sm font-semibold text-slate-800">Item</h3></div>
        <div v-if="!opp.items?.length" class="py-10 text-center text-sm text-slate-500">Belum ada item</div>
        <table v-else class="table-base">
          <thead>
            <tr>
              <th v-for="c in itemColumns" :key="c.key">{{ c.label }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in opp.items" :key="idx">
              <td>{{ (item.product as { name?: string })?.name || item.name || '-' }}</td>
              <td class="text-right">{{ item.quantity }}</td>
              <td class="text-right">{{ formatRupiah(item.unit_price) }}</td>
              <td class="text-right">{{ formatRupiah(item.discount) }}</td>
              <td class="text-right font-medium">{{ formatRupiah(item.subtotal) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Activities -->
      <div class="card">
        <div class="card-header">
          <h3 class="text-sm font-semibold text-slate-800">Activity</h3>
          <button v-if="canEdit" class="btn-primary btn-sm" @click="openCreateActivity">+ Tambah</button>
        </div>
        <EmptyState
          v-if="activities.length === 0"
          title="Belum ada activity"
          subtitle="Tambahkan activity pertama untuk opportunity ini"
        >
          <template #icon><Clock class="h-6 w-6" /></template>
        </EmptyState>
        <ul v-else class="divide-y divide-slate-100">
          <li v-for="a in activities" :key="a.id" class="flex items-start gap-3 px-5 py-3">
            <div
              class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
              :class="badgeColor(a.type)"
            >
              <component :is="activityIcons[a.type] ?? Clock" class="h-4 w-4" />
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-slate-800">{{ a.subject || a.type }}</p>
              <p v-if="a.description" class="mt-0.5 text-xs text-slate-500">{{ a.description }}</p>
              <div class="mt-1 flex flex-wrap items-center gap-2">
                <BaseBadge :value="a.type" size="xs" />
                <BaseBadge :value="a.status" size="xs" />
                <span class="text-[11px] text-slate-400">
                  {{ formatDate(a.scheduled_at || a.created_at, true) }} · {{ a.user?.name ?? '-' }}
                </span>
              </div>
            </div>
            <div v-if="canEdit" class="flex shrink-0 gap-1">
              <button class="rounded p-1 text-slate-400 hover:text-blue-600" @click="openEditActivity(a)">
                <Pencil class="h-3.5 w-3.5" />
              </button>
              <button class="rounded p-1 text-slate-400 hover:text-red-600" @click="removeActivity(a)">
                <Trash2 class="h-3.5 w-3.5" />
              </button>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- Logs -->
    <div class="card">
      <div class="card-header"><h3 class="text-sm font-semibold text-slate-800">Log Perubahan</h3></div>
      <EmptyState v-if="logs.length === 0" title="Belum ada log" subtitle="Perubahan pada opportunity akan tercatat di sini">
        <template #icon><Clock class="h-6 w-6" /></template>
      </EmptyState>
      <ul v-else class="divide-y divide-slate-100">
        <li v-for="l in logs" :key="l.id" class="px-5 py-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-medium text-slate-800">{{ l.action }} <span class="font-normal text-slate-500">— {{ l.user?.name ?? 'System' }}</span></p>
            <span class="text-xs text-slate-400">{{ formatDate(l.created_at, true) }}</span>
          </div>
          <p v-if="l.description" class="mt-0.5 text-xs text-slate-500">{{ l.description }}</p>
        </li>
      </ul>
    </div>

    <!-- Activity modal -->
    <BaseModal :open="activityModal" :title="editingActivity ? 'Edit Activity' : 'Tambah Activity'" @close="activityModal = false">
      <form class="space-y-4" @submit.prevent="saveActivity">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Tipe *</label>
            <select v-model="actForm.type" class="input">
              <option v-for="t in ACTIVITY_TYPES" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>
          <div>
            <label class="label">Status</label>
            <select v-model="actForm.status" class="input">
              <option v-for="s in ACTIVITY_STATUSES" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>
        </div>
        <div>
          <label class="label">Subjek *</label>
          <input v-model="actForm.subject" class="input" placeholder="Judul singkat activity" />
          <p v-if="actErrors.subject" class="mt-1 text-xs text-red-600">{{ actErrors.subject }}</p>
        </div>
        <div>
          <label class="label">Deskripsi</label>
          <textarea v-model="actForm.description" class="input" rows="2" placeholder="Catatan activity" />
        </div>
        <div>
          <label class="label">Jadwal</label>
          <input v-model="actForm.scheduled_at" type="date" class="input" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="activityModal = false">Batal</button>
          <button type="submit" class="btn-primary" :disabled="savingActivity">{{ savingActivity ? 'Menyimpan...' : 'Simpan' }}</button>
        </div>
      </form>
    </BaseModal>
  </div>
</template>
