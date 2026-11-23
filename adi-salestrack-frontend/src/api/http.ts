import axios, { type AxiosError, type AxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import router from '@/router'

declare module 'axios' {
  export interface AxiosRequestConfig {
    silent?: boolean
  }
}

export interface ApiMeta {
  current_page?: number
  last_page?: number
  per_page?: number
  total?: number
  [key: string]: unknown
}

export interface ApiResponse<T = unknown> {
  success: boolean
  message: string
  data: T
  meta?: ApiMeta
}

const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  timeout: 30000,
})

http.interceptors.request.use((config) => {
  const auth = useAuthStore()
  if (auth.token) {
    config.headers.Authorization = `Bearer ${auth.token}`
  }
  return config
})

http.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiResponse>) => {
    const ui = useUiStore()
    const status = error.response?.status
    const isSilent = (error.config as any)?.silent === true

    if (status === 401) {
      const auth = useAuthStore()
      auth.clearSession()
      if (router.currentRoute.value.name !== 'login') {
        router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
      }
    } else if (!isSilent) {
      if (status === 403) {
        ui.notify('error', 'Anda tidak memiliki izin untuk melakukan aksi ini')
      } else if (status === 429) {
        ui.notify('error', 'Terlalu banyak permintaan. Silakan coba lagi nanti')
      } else if (status && status >= 500) {
        ui.notify('error', error.response?.data?.message || 'Terjadi kesalahan pada server')
      } else if (!error.response) {
        ui.notify('error', 'Tidak dapat terhubung ke server. Periksa koneksi Anda')
      }
    }

    return Promise.reject(error)
  },
)

export interface PageData<T> {
  data: T[]
  meta?: ApiMeta
}

export async function apiGet<T = unknown>(url: string, config?: AxiosRequestConfig): Promise<T> {
  const res = await http.get<ApiResponse<T>>(url, config)
  return res.data.data
}

export async function apiList<T>(url: string, config?: AxiosRequestConfig): Promise<PageData<T>> {
  const res = await http.get<ApiResponse<T[]>>(url, config)
  return { data: res.data.data ?? [], meta: res.data.meta }
}

export async function apiPost<T = unknown>(
  url: string,
  body?: unknown,
  config?: AxiosRequestConfig,
): Promise<T> {
  const res = await http.post<ApiResponse<T>>(url, body, config)
  return res.data.data
}

export async function apiPut<T = unknown>(
  url: string,
  body?: unknown,
  config?: AxiosRequestConfig,
): Promise<T> {
  const res = await http.put<ApiResponse<T>>(url, body, config)
  return res.data.data
}

export async function apiDelete<T = unknown>(
  url: string,
  config?: AxiosRequestConfig,
): Promise<T> {
  const res = await http.delete<ApiResponse<T>>(url, config)
  return res.data.data
}

export { http }
