import { apiDelete, apiGet, apiList, apiPost, apiPut } from './http'

export interface TransactionItem {
  id?: number
  product_id: number | null
  product?: { id: number; name: string } | null
  name?: string
  quantity: number
  unit_price: number
  discount: number
  subtotal: number
}

export interface SalesTransaction {
  id: number
  opportunity_id: number | null
  opportunity?: { id: number; title: string } | null
  transaction_number?: string
  transaction_date?: string | null
  status: string
  total_amount: number
  items?: TransactionItem[]
  created_at?: string
}

interface RawTransaction {
  id: number
  transaction_number?: string | null
  opportunity?: { id: number; title: string } | null
  company?: { id: number; name: string } | null
  user?: { id: number; name: string } | null
  transaction_date?: string | null
  total_amount: number
  status: string
  items_count?: number
  created_at?: string
}

interface RawTransactionItem {
  id: number
  product?: { id: number; name: string; sku?: string; type?: string } | null
  quantity: number
  unit_price: number
  subtotal: number
}

function mapTransaction(raw: RawTransaction): SalesTransaction {
  return {
    id: raw.id,
    opportunity_id: raw.opportunity?.id ?? null,
    opportunity: raw.opportunity,
    transaction_number: raw.transaction_number ?? undefined,
    transaction_date: raw.transaction_date,
    status: raw.status,
    total_amount: raw.total_amount,
    created_at: raw.created_at,
  }
}

function mapItem(raw: RawTransactionItem): TransactionItem {
  return {
    id: raw.id,
    product_id: raw.product?.id ?? null,
    product: raw.product,
    quantity: raw.quantity,
    unit_price: raw.unit_price,
    discount: 0,
    subtotal: raw.subtotal,
  }
}

export const transactionsApi = {
  list: async (params?: Record<string, unknown>) => {
    const res = await apiList<RawTransaction>('/transactions', { params })
    return { data: res.data.map(mapTransaction), meta: res.meta }
  },
  get: async (id: number) => {
    const raw = await apiGet<RawTransaction>(`/transactions/${id}`)
    return mapTransaction(raw)
  },
  create: async (payload: Partial<SalesTransaction>) => {
    const items = (payload.items ?? []).map((i) => ({
      product_id: i.product_id,
      quantity: i.quantity,
      unit_price: i.unit_price,
    }))
    const body: Record<string, unknown> = {
      opportunity_id: payload.opportunity_id ?? null,
      transaction_date: payload.transaction_date ?? null,
      status: payload.status,
      items,
    }
    if (payload.opportunity_id) {
      try {
        const { opportunitiesApi } = await import('./opportunities')
        const opp = await opportunitiesApi.get(payload.opportunity_id)
        body.company_id = opp.company_id
      } catch {
        body.company_id = null
      }
    } else {
      body.company_id = null
    }
    const raw = await apiPost<RawTransaction>('/transactions', body)
    return mapTransaction(raw)
  },
  update: (id: number, payload: Partial<SalesTransaction>) =>
    apiPut<SalesTransaction>(`/transactions/${id}`, payload).then((r) =>
      mapTransaction(r as unknown as RawTransaction),
    ),
  remove: (id: number) => apiDelete(`/transactions/${id}`),
  items: async (id: number) => {
    const res = await apiGet<RawTransactionItem[]>(`/transactions/${id}/items`)
    return res.map(mapItem)
  },
}