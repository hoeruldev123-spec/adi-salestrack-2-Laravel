<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { transactionsApi, type TransactionItem } from '@/api/transactions'
import { opportunitiesApi, type Opportunity } from '@/api/opportunities'
import { productsApi, type Product } from '@/api/products'
import { useUiStore } from '@/stores/ui'
import { useFormat } from '@/composables/useFormat'
import { getApiErrorMessage, getFieldError } from '@/utils/form'
import { TRANSACTION_STATUSES } from '@/constants'
import { Plus, Trash2 } from 'lucide-vue-next'

const router = useRouter()
const ui = useUiStore()
const { formatRupiah } = useFormat()

const loading = ref(true)
const saving = ref(false)
const fieldErrors = reactive<Record<string, string>>({})

const opportunities = ref<Opportunity[]>([])
const products = ref<Product[]>([])

const form = reactive({
  opportunity_id: null as number | null,
  transaction_date: new Date().toISOString().slice(0, 10),
  status: 'PAID',
  items: [] as TransactionItem[],
})

const total = computed(() => form.items.reduce((sum, i) => sum + (i.subtotal || 0), 0))

function onOpportunityChange() {
  const op = opportunities.value.find((o) => o.id === form.opportunity_id)
  if (op?.items?.length) {
    form.items = op.items.map((i) => ({
      product_id: i.product_id,
      name: i.name,
      quantity: i.quantity,
      unit_price: i.unit_price,
      discount: i.discount,
      subtotal: i.subtotal,
    }))
  }
}

function addItem() {
  form.items.push({ product_id: null, quantity: 1, unit_price: 0, discount: 0, subtotal: 0 })
}

function removeItem(index: number) {
  form.items.splice(index, 1)
}

function recalcItem(item: TransactionItem) {
  const product = products.value.find((p) => p.id === item.product_id)
  if (product && !item.unit_price) item.unit_price = product.price
  item.quantity = Number(item.quantity) || 1
  item.unit_price = Number(item.unit_price) || 0
  item.discount = Number(item.discount) || 0
  item.subtotal = item.quantity * item.unit_price - item.discount
}

async function save() {
  saving.value = true
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  try {
    await transactionsApi.create({
      opportunity_id: form.opportunity_id,
      transaction_date: form.transaction_date,
      status: form.status,
      total_amount: total.value,
      items: form.items,
    })
    ui.notify('success', 'Transaction berhasil dibuat')
    router.push('/transactions')
  } catch (e) {
    for (const f of ['opportunity_id', 'transaction_date', 'status', 'total_amount', 'items']) {
      const msg = getFieldError(e, f)
      if (msg) fieldErrors[f] = msg
    }
    if (!Object.keys(fieldErrors).length) ui.notify('error', getApiErrorMessage(e))
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    const [opRes, prodRes] = await Promise.all([
      opportunitiesApi.list({ per_page: 100 }),
      productsApi.list({ per_page: 100 }),
    ])
    opportunities.value = opRes.data
    products.value = prodRes.data
    addItem()
  } catch (e) {
    ui.notify('error', getApiErrorMessage(e))
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-4">
    <div class="card p-6">
      <h2 class="mb-5 text-base font-semibold text-slate-900">Buat Transaction Baru</h2>
      <form class="space-y-4" @submit.prevent="save">
        <div v-if="loading" class="py-10 text-center text-sm text-slate-500">Memuat data...</div>
        <template v-else>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="label">Opportunity *</label>
              <select v-model="form.opportunity_id" class="input" @change="onOpportunityChange">
                <option :value="null">Pilih opportunity</option>
                <option v-for="o in opportunities" :key="o.id" :value="o.id">
                  {{ o.title }} ({{ formatRupiah(o.value) }})
                </option>
              </select>
              <p v-if="fieldErrors.opportunity_id" class="mt-1 text-xs text-red-600">{{ fieldErrors.opportunity_id }}</p>
            </div>
            <div>
              <label class="label">Tanggal *</label>
              <input v-model="form.transaction_date" type="date" class="input" />
              <p v-if="fieldErrors.transaction_date" class="mt-1 text-xs text-red-600">{{ fieldErrors.transaction_date }}</p>
            </div>
          </div>

          <div>
            <label class="label">Status</label>
            <select v-model="form.status" class="input !w-48">
              <option v-for="s in TRANSACTION_STATUSES" :key="s" :value="s">{{ s }}</option>
            </select>
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
                    <th class="w-28">Harga</th>
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
                    <td><input v-model.number="item.quantity" type="number" min="1" class="input !py-1.5 text-xs" @change="recalcItem(item)" /></td>
                    <td><input v-model.number="item.unit_price" type="number" min="0" class="input !py-1.5 text-xs" @change="recalcItem(item)" /></td>
                    <td><input v-model.number="item.discount" type="number" min="0" class="input !py-1.5 text-xs" @change="recalcItem(item)" /></td>
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
              Total: <span class="font-semibold">{{ formatRupiah(total) }}</span>
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
