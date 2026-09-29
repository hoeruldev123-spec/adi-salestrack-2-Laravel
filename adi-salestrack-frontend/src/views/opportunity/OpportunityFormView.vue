<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { opportunitiesApi, type OpportunityItem } from '@/api/opportunities'
import { companiesApi, type Company } from '@/api/companies'
import { usersApi, type User } from '@/api/users'
import { principalsApi, type Principal } from '@/api/principals'
import { productsApi, type Product } from '@/api/products'
import { useUiStore } from '@/stores/ui'
import { useFormat } from '@/composables/useFormat'
import { getApiErrorMessage, getFieldError } from '@/utils/form'
import {
  OPPORTUNITY_STAGES,
  OPPORTUNITY_SOURCES,
  STAGE_PROBABILITY,
} from '@/constants'
import { Plus, Trash2 } from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const { formatRupiah } = useFormat()

const isEdit = computed(() => !!route.params.id)
const opportunityId = computed(() => Number(route.params.id))

const loading = ref(false)
const saving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})

const companies = ref<Company[]>([])
const users = ref<User[]>([])
const principals = ref<Principal[]>([])
const products = ref<Product[]>([])

const form = reactive({
  company_id: null as number | null,
  sales_id: null as number | null,
  principal_id: null as number | null,
  title: '',
  description: '',
  value: 0,
  stage: 'LEAD',
  probability: STAGE_PROBABILITY.LEAD,
  expected_close_date: '',
  source: 'OTHER',
  items: [] as OpportunityItem[],
})

const autoValue = computed(() => form.items.reduce((sum, i) => sum + (i.subtotal || 0), 0))

function onStageChange() {
  form.probability = STAGE_PROBABILITY[form.stage] ?? form.probability
}

function addItem() {
  form.items.push({ product_id: null, quantity: 1, unit_price: 0, discount: 0, subtotal: 0 })
}

function removeItem(index: number) {
  form.items.splice(index, 1)
}

function recalcItem(item: OpportunityItem) {
  const product = products.value.find((p) => p.id === item.product_id)
  if (product && !item.unit_price) item.unit_price = product.price
  item.quantity = Number(item.quantity) || 1
  item.unit_price = Number(item.unit_price) || 0
  item.discount = Number(item.discount) || 0
  item.subtotal = item.quantity * item.unit_price - item.discount
  form.value = autoValue.value
}

async function loadForEdit() {
  loading.value = true
  try {
    const op = await opportunitiesApi.get(opportunityId.value)
    Object.assign(form, {
      company_id: op.company_id,
      sales_id: op.sales_id,
      principal_id: op.principal_id,
      title: op.title,
      description: op.description ?? '',
      value: op.value,
      stage: op.stage,
      probability: op.probability,
      expected_close_date: op.expected_close_date ? String(op.expected_close_date).slice(0, 10) : '',
      source: op.source ?? 'OTHER',
      items: (op.items ?? []).map((i) => ({ ...i })),
    })
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
    router.back()
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  try {
    const payload = {
      ...form,
      value: form.items.length ? autoValue.value : form.value,
      expected_close_date: form.expected_close_date || null,
      company_id: form.company_id || null,
      sales_id: form.sales_id || null,
      principal_id: form.principal_id || null,
    }
    if (isEdit.value) {
      await opportunitiesApi.update(opportunityId.value, payload)
      ui.notify('success', 'Opportunity berhasil diperbarui')
    } else {
      await opportunitiesApi.create(payload)
      ui.notify('success', 'Opportunity berhasil dibuat')
    }
    router.push(isEdit.value ? `/opportunities/${opportunityId.value}` : '/opportunities')
  } catch (e) {
    for (const f of ['title', 'company_id', 'sales_id', 'value', 'stage', 'expected_close_date']) {
      const msg = getFieldError(e, f)
      if (msg) fieldErrors[f] = msg
    }
    const msg = getApiErrorMessage(e)
    if (!Object.keys(fieldErrors).length) ui.notify('error', msg)
    else ui.notify('error', msg)
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  const [compRes, userRes, prinRes, prodRes] = await Promise.all([
    companiesApi.list({ per_page: 100 }),
    usersApi.list({ per_page: 100 }),
    principalsApi.list({ per_page: 100 }),
    productsApi.list({ per_page: 100 }),
  ])
  companies.value = compRes.data
  users.value = userRes.data
  principals.value = prinRes.data
  products.value = prodRes.data
  if (isEdit.value) await loadForEdit()
  else addItem()
})
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-4">
    <div class="card p-6">
      <h2 class="mb-5 text-base font-semibold text-slate-900">{{ isEdit ? 'Edit Opportunity' : 'Buat Opportunity Baru' }}</h2>
      <form class="space-y-4" @submit.prevent="save">
        <div v-if="loading" class="py-10 text-center text-sm text-slate-500">Memuat data...</div>
        <template v-else>
          <div>
            <label class="label">Judul *</label>
            <input v-model="form.title" class="input" placeholder="Nama opportunity" />
            <p v-if="fieldErrors.title" class="mt-1 text-xs text-red-600">{{ fieldErrors.title }}</p>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="label">Company *</label>
              <select v-model="form.company_id" class="input">
                <option :value="null">Pilih company</option>
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
              <p v-if="fieldErrors.company_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.company_id }}</p>
            </div>
            <div>
              <label class="label">Sales Owner *</label>
              <select v-model="form.sales_id" class="input">
                <option :value="null">Pilih sales</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <p v-if="fieldErrors.sales_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.sales_id }}</p>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="label">Principal</label>
              <select v-model="form.principal_id" class="input">
                <option :value="null">Pilih principal</option>
                <option v-for="p in principals" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
            <div>
              <label class="label">Sumber</label>
              <select v-model="form.source" class="input">
                <option v-for="s in OPPORTUNITY_SOURCES" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
          </div>

          <div>
            <label class="label">Deskripsi</label>
            <textarea v-model="form.description" class="input" rows="3" placeholder="Deskripsi opportunity" />
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="label">Stage</label>
              <select v-model="form.stage" class="input" @change="onStageChange">
                <option v-for="s in OPPORTUNITY_STAGES" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
            <div>
              <label class="label">Probability (%)</label>
              <input v-model.number="form.probability" type="number" min="0" max="100" class="input" />
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="label">Expected Close Date</label>
              <input v-model="form.expected_close_date" type="date" class="input" />
              <p v-if="fieldErrors.expected_close_date" class="mt-1 text-xs text-red-600">{{ fieldErrors.expected_close_date }}</p>
            </div>
            <div>
              <label class="label">Nilai (manual jika tanpa item)</label>
              <input v-model.number="form.value" type="number" min="0" class="input" :disabled="form.items.length > 0" />
              <p v-if="fieldErrors.value" class="mt-1 text-xs text-red-600">{{ fieldErrors.value }}</p>
            </div>
          </div>

          <div>
            <div class="mb-2 flex items-center justify-between">
              <label class="label !mb-0">Item</label>
              <button type="button" class="btn-secondary btn-sm" @click="addItem">
                <Plus class="h-3.5 w-3.5" /> Tambah Item
              </button>
            </div>
            <div v-if="form.items.length" class="overflow-x-auto rounded-lg border border-slate-200">
              <table class="table-base">
                <thead>
                  <tr>
                    <th>Product</th>
                    <th class="w-20">Qty</th>
                    <th class="w-28">Unit Price</th>
                    <th class="w-24">Disc</th>
                    <th class="w-28 text-right">Subtotal</th>
                    <th class="w-10"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in form.items" :key="idx">
                    <td>
                      <select v-model="item.product_id" class="input !py-1.5 text-xs" @change="recalcItem(item)">
                        <option :value="null">Pilih product</option>
                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} — {{ formatRupiah(p.price) }}</option>
                      </select>
                    </td>
                    <td>
                      <input v-model.number="item.quantity" type="number" min="1" class="input !py-1.5 text-xs" @change="recalcItem(item)" />
                    </td>
                    <td>
                      <input v-model.number="item.unit_price" type="number" min="0" class="input !py-1.5 text-xs" @change="recalcItem(item)" />
                    </td>
                    <td>
                      <input v-model.number="item.discount" type="number" min="0" class="input !py-1.5 text-xs" @change="recalcItem(item)" />
                    </td>
                    <td class="text-right text-xs font-medium">{{ formatRupiah(item.subtotal) }}</td>
                    <td>
                      <button type="button" class="rounded p-1 text-slate-400 hover:text-red-600" @click="removeItem(idx)">
                        <Trash2 class="h-3.5 w-3.5" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-if="form.items.length" class="mt-2 text-right text-sm">
              Total: <span class="font-semibold">{{ formatRupiah(autoValue) }}</span>
            </p>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn-secondary" @click="router.back()">Batal</button>
            <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
          </div>
        </template>
      </form>
    </div>
  </div>
</template>
