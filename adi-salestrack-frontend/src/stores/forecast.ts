import { defineStore } from 'pinia'
import { ref } from 'vue'
import {
  forecastsApi,
  type ForecastRun,
  type ForecastScenario,
} from '@/api/forecasts'

export const useForecastStore = defineStore('forecast', () => {
  const runs = ref<ForecastRun[]>([])
  const currentRun = ref<ForecastRun | null>(null)
  const scenarios = ref<ForecastScenario[]>([])
  const loading = ref(false)

  async function fetchRuns(params?: Record<string, unknown>) {
    loading.value = true
    try {
      const res = await forecastsApi.list(params)
      runs.value = res.data
    } finally {
      loading.value = false
    }
  }

  async function fetchRun(id: number) {
    loading.value = true
    try {
      currentRun.value = await forecastsApi.get(id)
      return currentRun.value
    } finally {
      loading.value = false
    }
  }

  async function createRun(payload: Partial<ForecastRun>) {
    return forecastsApi.create(payload)
  }

  async function fetchScenarios(id: number) {
    scenarios.value = await forecastsApi.scenarios(id)
    return scenarios.value
  }

  async function createScenario(runId: number, payload: Partial<ForecastScenario>) {
    return forecastsApi.createScenario(runId, payload)
  }

  async function removeScenario(runId: number, scenarioId: number) {
    await forecastsApi.removeScenario(runId, scenarioId)
  }

  return {
    runs,
    currentRun,
    scenarios,
    loading,
    fetchRuns,
    fetchRun,
    createRun,
    fetchScenarios,
    createScenario,
    removeScenario,
  }
})
