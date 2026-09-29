import { apiGet, apiPost } from './http'
import type { RoleObject } from '@/constants'

export interface LoginResponse {
  token: string
  user: {
    id: number
    name: string
    email: string
    role: string | RoleObject
    status: string
  }
}

export const authApi = {
  login: (email: string, password: string) =>
    apiPost<LoginResponse>('/auth/login', { email, password }),
  logout: () => apiPost('/auth/logout'),
  me: () => apiGet('/auth/me'),
}
