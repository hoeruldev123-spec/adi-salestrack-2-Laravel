import { apiDelete, apiGet, apiList, apiPost, apiPut } from './http'

export interface Product {
  id: number
  principal_id: number | null
  principal?: { id: number; name: string } | null
  category_id?: number | null
  category?: { id: number; name: string } | null
  name: string
  product_type?: string
  sku?: string
  type: string
  description?: string
  unit?: string
  price: number
  status: string
  is_active?: boolean
  specifications?: ProductSpecification[]
  price_lists?: PriceList[]
  specifications_count?: number
  price_lists_count?: number
  created_at?: string
}

export interface ProductSpecification {
  id: number
  product_id: number
  specification_name: string
  specification_value: string
  unit?: string | null
}

export interface PriceList {
  id: number
  product_id: number
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

export const productsApi = {
  list: (params?: Record<string, unknown>) =>
    apiList<Product>('/products', { params }),
  get: (id: number) => apiGet<Product>(`/products/${id}`),
  create: (payload: Partial<Product>) => apiPost<Product>('/products', payload),
  update: (id: number, payload: Partial<Product>) => apiPut<Product>(`/products/${id}`, payload),
  remove: (id: number) => apiDelete(`/products/${id}`),
  specifications: (id: number, params?: Record<string, unknown>) =>
    apiList<ProductSpecification>(`/products/${id}/specifications`, { params }),
  createSpecification: (id: number, payload: Partial<ProductSpecification>) =>
    apiPost<ProductSpecification>(`/products/${id}/specifications`, payload),
  updateSpecification: (id: number, payload: Partial<ProductSpecification>) =>
    apiPut<ProductSpecification>(`/product-specifications/${id}`, payload),
  removeSpecification: (id: number) => apiDelete(`/product-specifications/${id}`),
}
