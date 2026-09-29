import { apiDelete, apiList, apiPost, apiPut } from './http'

export interface Principal {
  id: number
  name: string
  description?: string
  website?: string
  status: string
  created_at?: string
}

export const principalsApi = {
  list: (params?: Record<string, unknown>) =>
    apiList<Principal>('/principals', { params }),
  create: (payload: Partial<Principal>) => apiPost<Principal>('/principals', payload),
  update: (id: number, payload: Partial<Principal>) => apiPut<Principal>(`/principals/${id}`, payload),
  remove: (id: number) => apiDelete(`/principals/${id}`),
}
