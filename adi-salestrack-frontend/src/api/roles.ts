import { apiGet } from './http'

export interface Role {
  id: number
  name: string
  label?: string
  description?: string
}

export const rolesApi = {
  list: () => apiGet<Role[]>('/roles'),
}
