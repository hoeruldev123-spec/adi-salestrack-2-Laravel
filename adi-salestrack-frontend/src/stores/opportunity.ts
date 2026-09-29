import { defineStore } from 'pinia'
import { ref } from 'vue'
import { opportunitiesApi, type Opportunity } from '@/api/opportunities'

export const useOpportunityStore = defineStore('opportunity', () => {
  const list = ref<Opportunity[]>([])
  const current = ref<Opportunity | null>(null)
  const loading = ref(false)

  async function fetchList(params?: Record<string, unknown>) {
    loading.value = true
    try {
      const res = await opportunitiesApi.list(params)
      list.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function fetchDetail(id: number) {
    loading.value = true
    try {
      current.value = await opportunitiesApi.get(id)
      return current.value
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Partial<Opportunity>) {
    const res = await opportunitiesApi.create(payload)
    return res
  }

  async function update(id: number, payload: Partial<Opportunity>) {
    const res = await opportunitiesApi.update(id, payload)
    current.value = res
    return res
  }

  async function remove(id: number) {
    await opportunitiesApi.remove(id)
  }

  return { list, current, loading, fetchList, fetchDetail, create, update, remove }
})
