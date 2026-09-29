import type { AxiosError } from 'axios'

export interface ApiErrorResponse {
  message?: string
  errors?: Record<string, string[]>
}

export function getFieldError(e: unknown, field: string): string | undefined {
  const err = e as AxiosError<ApiErrorResponse>
  const errors = err.response?.data?.errors
  if (errors?.[field]?.length) return errors[field][0]
  return undefined
}

export function getApiErrorMessage(e: unknown): string {
  const err = e as AxiosError<ApiErrorResponse>
  if (!err.response) return 'Tidak dapat terhubung ke server'
  if (err.response.status === 422 && err.response.data?.errors) {
    return Object.values(err.response.data.errors)
      .flat()
      .slice(0, 3)
      .join(', ')
  }
  return err.response.data?.message || err.message || 'Terjadi kesalahan'
}
