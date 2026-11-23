import { apiDelete, apiList, apiPost, apiPut } from './http'

export interface PriceList {
  id: number
  product_id: number | null
  product?: { id: number; name: string; sku?: string } | null
  pricing_type: string
  unit_price: number
  currency: string
  billing_period: string
  effective_date?: string | null
  expiry_date?: string | null
  is_active: boolean
  created_at?: string
}

export const priceListsApi = {
  list: (params?: Record<string, unknown>) =>
    apiList<PriceList>('/price-lists', { params }),
  create: (payload: Partial<PriceList>) => apiPost<PriceList>('/price-lists', payload),
  update: (id: number, payload: Partial<PriceList>) =>
    apiPut<PriceList>(`/price-lists/${id}`, payload),
  remove: (id: number) => apiDelete(`/price-lists/${id}`),
}
