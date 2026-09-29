import { apiDelete, apiList, apiPost, apiPut } from './http'

export interface Company {
  id: number
  name: string
  industry?: string
  email?: string
  phone?: string
  address?: string
  status: string
  created_at?: string
}

export const companiesApi = {
  list: (params?: Record<string, unknown>) =>
    apiList<Company>('/companies', { params }),
  create: (payload: Partial<Company>) => apiPost<Company>('/companies', payload),
  update: (id: number, payload: Partial<Company>) => apiPut<Company>(`/companies/${id}`, payload),
  remove: (id: number) => apiDelete(`/companies/${id}`),
}
