import { apiDelete, apiList, apiPost, apiPut } from './http'

export interface Product {
  id: number
  principal_id: number | null
  principal?: { id: number; name: string } | null
  name: string
  sku?: string
  type: string
  price: number
  status: string
  created_at?: string
}

export const productsApi = {
  list: (params?: Record<string, unknown>) =>
    apiList<Product>('/products', { params }),
  create: (payload: Partial<Product>) => apiPost<Product>('/products', payload),
  update: (id: number, payload: Partial<Product>) => apiPut<Product>(`/products/${id}`, payload),
  remove: (id: number) => apiDelete(`/products/${id}`),
}
