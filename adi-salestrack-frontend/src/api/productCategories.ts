import { apiDelete, apiList, apiPost, apiPut } from './http'

export interface ProductCategory {
  id: number
  name: string
  description?: string | null
  products_count?: number
  created_at?: string
}

export const productCategoriesApi = {
  list: (params?: Record<string, unknown>) =>
    apiList<ProductCategory>('/product-categories', { params }),
  create: (payload: Partial<ProductCategory>) =>
    apiPost<ProductCategory>('/product-categories', payload),
  update: (id: number, payload: Partial<ProductCategory>) =>
    apiPut<ProductCategory>(`/product-categories/${id}`, payload),
  remove: (id: number) => apiDelete(`/product-categories/${id}`),
}
