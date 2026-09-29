import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi, type LoginResponse } from '@/api/auth'
import { roleNameOf, type Role, type RoleObject } from '@/constants'

const TOKEN_KEY = 'adtoken'

export interface AuthUser {
  id: number
  name: string
  email: string
  role: Role | RoleObject | string
  status: string
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem(TOKEN_KEY))
  const user = ref<AuthUser | null>(null)

  const isAuthenticated = computed(() => !!token.value)
  const roleName = computed(() => roleNameOf(user.value?.role))
  const isAdmin = computed(() => roleName.value === 'ADMIN')
  const isSales = computed(() => roleName.value === 'SALES')
  const isSalesManager = computed(() => roleName.value === 'SALES_MANAGER')
  const isManagement = computed(() => roleName.value === 'MANAGEMENT')

  function setSession(res: LoginResponse) {
    token.value = res.token
    user.value = res.user
    localStorage.setItem(TOKEN_KEY, res.token)
  }

  async function login(email: string, password: string) {
    const res = await authApi.login(email, password)
    setSession(res)
    return res
  }

  async function fetchMe() {
    try {
      user.value = (await authApi.me()) as AuthUser
    } catch {
      clearSession()
    }
  }

  async function logout() {
    try {
      await authApi.logout()
    } catch {
      // ignore
    }
    clearSession()
  }

  function clearSession() {
    token.value = null
    user.value = null
    localStorage.removeItem(TOKEN_KEY)
  }

  function hasRole(roles?: string[]) {
    if (!roles || roles.length === 0) return true
    return roles.includes(roleName.value)
  }

  function canAccess(menu: string): boolean {
    const matrix: Record<string, Role[]> = {
      dashboard: ['ADMIN', 'SALES', 'SALES_MANAGER', 'MANAGEMENT'],
      master: ['ADMIN'],
      opportunities: ['ADMIN', 'SALES', 'SALES_MANAGER', 'MANAGEMENT'],
      activities: ['ADMIN', 'SALES', 'SALES_MANAGER', 'MANAGEMENT'],
      transactions: ['ADMIN', 'SALES', 'SALES_MANAGER', 'MANAGEMENT'],
      analytics: ['ADMIN', 'SALES_MANAGER', 'MANAGEMENT'],
      forecast: ['ADMIN', 'SALES_MANAGER', 'MANAGEMENT'],
      scenario: ['ADMIN', 'SALES_MANAGER'],
      evaluation: ['ADMIN', 'SALES_MANAGER', 'MANAGEMENT'],
    }
    return (matrix[menu] ?? []).includes(roleName.value as Role)
  }

  return {
    token,
    user,
    isAuthenticated,
    roleName,
    isAdmin,
    isSales,
    isSalesManager,
    isManagement,
    login,
    logout,
    fetchMe,
    setSession,
    clearSession,
    hasRole,
    canAccess,
  }
})
