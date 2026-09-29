import { apiDelete, apiList, apiPost, apiPut } from './http'
import type { RoleObject } from '@/constants'

export interface User {
  id: number
  name: string
  email: string
  role: string | RoleObject
  status: string
  created_at?: string
}

export const usersApi = {
  list: (params?: Record<string, unknown>) =>
    apiList<User>('/users', { params }),
  create: (payload: { name: string; email: string; role_id: number; status: string; password: string }) =>
    apiPost<User>('/users', payload),
  update: (id: number, payload: { name: string; email: string; role_id: number; status: string }) =>
    apiPut<User>(`/users/${id}`, payload),
  remove: (id: number) => apiDelete(`/users/${id}`),
}
