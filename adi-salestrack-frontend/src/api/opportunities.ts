import { apiDelete, apiGet, apiList, apiPost, apiPut } from './http'
import type { Company } from './companies'
import type { User } from './users'
import type { Principal } from './principals'

export interface OpportunityItem {
  id?: number
  product_id: number | null
  product?: { id: number; name: string; price?: number; sku?: string; type?: string }
  name?: string
  quantity: number
  unit_price: number
  discount: number
  subtotal: number
}

export interface Opportunity {
  id: number
  company_id: number | null
  company?: Company | null
  sales_id: number | null
  sales?: User | null
  principal_id: number | null
  principal?: Principal | null
  title: string
  description?: string
  value: number
  stage: string
  probability: number
  expected_close_date?: string | null
  source?: string
  status: string
  items?: OpportunityItem[]
  created_at?: string
  updated_at?: string
}

export interface OpportunityLog {
  id: number
  action: string
  description?: string
  changes?: Record<string, unknown>
  user?: { id: number; name: string }
  created_at?: string
}

interface RawOpportunity {
  id: number
  company?: { id: number; name: string } | null
  user?: { id: number; name: string; email?: string } | null
  principal?: { id: number; name: string } | null
  title: string
  description?: string | null
  value: number
  stage: string
  status: string
  probability: number
  expected_close_date?: string | null
  source?: string | null
  created_at?: string
  updated_at?: string
}

function mapOpp(raw: RawOpportunity): Opportunity {
  return {
    id: raw.id,
    company_id: raw.company?.id ?? null,
    company: raw.company ? { id: raw.company.id, name: raw.company.name } as Company : null,
    sales_id: raw.user?.id ?? null,
    sales: raw.user ? { id: raw.user.id, name: raw.user.name, email: raw.user.email ?? '' } as unknown as User : null,
    principal_id: raw.principal?.id ?? null,
    principal: raw.principal ? { id: raw.principal.id, name: raw.principal.name, status: 'ACTIVE' } as Principal : null,
    title: raw.title,
    description: raw.description ?? '',
    value: raw.value,
    stage: raw.stage,
    probability: raw.probability,
    expected_close_date: raw.expected_close_date ?? null,
    source: raw.source ?? '',
    status: raw.status,
    created_at: raw.created_at,
    updated_at: raw.updated_at,
  }
}

interface RawOpportunityItem {
  id: number
  product?: { id: number; name: string; sku?: string; type?: string } | null
  quantity: number
  unit_price: number
  discount: number
  subtotal: number
}

function mapItem(raw: RawOpportunityItem): OpportunityItem {
  return {
    id: raw.id,
    product_id: raw.product?.id ?? null,
    product: raw.product ?? undefined,
    quantity: raw.quantity,
    unit_price: raw.unit_price,
    discount: raw.discount,
    subtotal: raw.subtotal,
  }
}

function mapLog(raw: Record<string, unknown>): OpportunityLog {
  return {
    id: Number(raw.id),
    action: String(raw.field_name ?? 'UPDATE'),
    description: raw.description ? String(raw.description) : undefined,
    user: raw.user ? { id: Number((raw.user as { id: number }).id), name: String((raw.user as { name: string }).name) } : undefined,
    created_at: raw.created_at ? String(raw.created_at) : undefined,
  }
}

export const opportunitiesApi = {
  list: async (params?: Record<string, unknown>) => {
    const res = await apiList<RawOpportunity>('/opportunities', { params })
    return { data: res.data.map(mapOpp), meta: res.meta }
  },
  get: async (id: number) => {
    const [raw, items] = await Promise.all([
      apiGet<RawOpportunity>(`/opportunities/${id}`),
      opportunitiesApi.items(id).catch(() => [] as OpportunityItem[]),
    ])
    return { ...mapOpp(raw), items }
  },
  create: (payload: Partial<Opportunity>) => {
    const body = toBackendPayload(payload)
    return apiPost<Opportunity>('/opportunities', body).then(mapOpp)
  },
  update: (id: number, payload: Partial<Opportunity>) => {
    const body = toBackendPayload(payload)
    return apiPut<Opportunity>(`/opportunities/${id}`, body).then(mapOpp)
  },
  remove: (id: number) => apiDelete(`/opportunities/${id}`),
  items: async (id: number) => {
    const res = await apiGet<RawOpportunityItem[]>(`/opportunities/${id}/items`)
    return res.map(mapItem)
  },
  logs: async (id: number) => {
    const res = await apiGet<Record<string, unknown>[]>(`/opportunities/${id}/logs`)
    return res.map(mapLog)
  },
}

function toBackendPayload(payload: Partial<Opportunity>): Record<string, unknown> {
  const body: Record<string, unknown> = {
    company_id: payload.company_id ?? null,
    user_id: payload.sales_id ?? null,
    principal_id: payload.principal_id ?? null,
    title: payload.title,
    description: payload.description ?? null,
    value: payload.value,
    stage: payload.stage,
    probability: payload.probability,
    expected_close_date: payload.expected_close_date || null,
    source: payload.source || null,
  }
  if (payload.items && payload.items.length > 0) {
    body.items = payload.items.map((i) => ({
      product_id: i.product_id,
      quantity: i.quantity,
      unit_price: i.unit_price,
      discount: i.discount ?? 0,
    }))
  }
  return body
}