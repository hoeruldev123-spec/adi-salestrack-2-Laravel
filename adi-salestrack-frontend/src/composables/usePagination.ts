import { computed, reactive, watch } from 'vue'
import type { ApiMeta } from '@/api/http'

export interface PageResult<T> {
  data: T[]
  meta?: ApiMeta
}

export function usePagination<T>(fetcher: (params: Record<string, unknown>) => Promise<PageResult<T>>) {
  const state = reactive({
    items: [] as T[],
    loading: false,
    error: null as string | null,
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 10,
    search: '',
    pages: computed<number[]>(() => {
      const arr: number[] = []
      const from = Math.max(1, state.currentPage - 2)
      const to = Math.min(state.lastPage, from + 4)
      for (let i = from; i <= to; i++) arr.push(i)
      return arr
    }),
  })

  async function load() {
    state.loading = true
    state.error = null
    try {
      const params: Record<string, unknown> = {
        page: state.currentPage,
        per_page: state.perPage,
      }
      if (state.search.trim()) params.search = state.search.trim()
      const res = await fetcher(params)
      state.items = res.data as typeof state.items
      state.lastPage = res.meta?.last_page ?? 1
      state.total = res.meta?.total ?? 0
      if (state.currentPage > state.lastPage) state.currentPage = state.lastPage
    } catch (e) {
      state.error = (e as Error).message || 'Gagal memuat data'
    } finally {
      state.loading = false
    }
  }

  function go(page: number) {
    if (page < 1 || page > state.lastPage || page === state.currentPage) return
    state.currentPage = page
  }

  watch(
    () => [state.currentPage, state.perPage],
    () => load(),
  )

  return Object.assign(state, { load, go })
}
