import { apiDelete, apiGet, apiList, apiPost } from './http'

export interface ForecastRun {
  id: number
  name: string
  forecast_type: string
  period_start?: string | null
  period_end?: string | null
  simulation_count: number
  confidence_level: number
  target_revenue?: number | null
  status: string
  error_message?: string | null
  created_by?: { id: number; name: string } | null
  started_at?: string | null
  completed_at?: string | null
  created_at?: string
}

export interface ForecastResult {
  min: number
  max: number
  mean: number
  median: number
  percentiles: Record<string, number>
  probability_target: number | null
  histogram?: Array<{ bin_start: number; bin_end: number; count: number }>
  series?: number[]
  target_revenue?: number | null
}

export interface ForecastScenario {
  id: number
  forecast_run_id: number
  name: string
  description?: string | null
  win_rate_adjustment?: number | null
  revenue_adjustment?: number | null
  opportunity_adjustment?: number | null
  target_revenue?: number | null
  created_at?: string
}

export interface ScenarioResult {
  scenario_id: number
  scenario_name: string
  current?: Record<string, number>
  scenario?: Record<string, number>
  potential_increase?: number
  probability_target?: number
}

export interface EvaluationResult {
  status?: string
  message?: string
  mae: number
  mape: number
  rmse: number
  actual: Array<{ period: string; value: number }>
  forecast: Array<{ period: string; value: number }>
}

interface RawForecastRun {
  id: number
  name: string
  forecast_type: string
  period_start?: string | null
  period_end?: string | null
  simulation_count: number
  confidence_level: number
  target_revenue?: number | null
  status: string
  created_by?: { id: number; name: string } | null
  started_at?: string | null
  completed_at?: string | null
  created_at?: string
}

interface RawForecastResult {
  minimum: number
  maximum: number
  mean: number
  median: number
  p10: number
  p25: number
  p50: number
  p75: number
  p90: number
  p95: number
  target_probability: number | null
}

const PERCENTILE_KEYS = ['P10', 'P25', 'P50', 'P75', 'P90', 'P95'] as const

function mapRun(raw: RawForecastRun): ForecastRun {
  return {
    id: raw.id,
    name: raw.name,
    forecast_type: raw.forecast_type,
    period_start: raw.period_start,
    period_end: raw.period_end,
    simulation_count: raw.simulation_count,
    confidence_level: raw.confidence_level,
    target_revenue: raw.target_revenue,
    status: raw.status,
    created_by: raw.created_by,
    started_at: raw.started_at,
    completed_at: raw.completed_at,
    created_at: raw.created_at,
  }
}

function mapResult(raw: RawForecastResult): ForecastResult {
  const percentiles: Record<string, number> = {}
  const map: Record<string, number> = {
    P10: raw.p10,
    P25: raw.p25,
    P50: raw.p50,
    P75: raw.p75,
    P90: raw.p90,
    P95: raw.p95,
  }
  for (const key of PERCENTILE_KEYS) {
    percentiles[key] = Number(map[key] ?? 0)
  }
  return {
    min: raw.minimum,
    max: raw.maximum,
    mean: raw.mean,
    median: raw.median,
    percentiles,
    probability_target: raw.target_probability,
  }
}

function mapScenario(raw: Record<string, unknown>): ForecastScenario {
  return {
    id: Number(raw.id),
    forecast_run_id: Number(raw.forecast_run_id),
    name: String(raw.name ?? ''),
    description: raw.description ? String(raw.description) : null,
    win_rate_adjustment: raw.win_rate_adjustment != null ? Number(raw.win_rate_adjustment) : null,
    revenue_adjustment: raw.revenue_adjustment != null ? Number(raw.revenue_adjustment) : null,
    opportunity_adjustment: raw.opportunity_adjustment != null ? Number(raw.opportunity_adjustment) : null,
    target_revenue: raw.target_revenue != null ? Number(raw.target_revenue) : null,
    created_at: raw.created_at ? String(raw.created_at) : undefined,
  }
}

export const forecastsApi = {
  list: async (params?: Record<string, unknown>) => {
    const res = await apiList<RawForecastRun>('/forecast-runs', { params })
    return { data: res.data.map(mapRun), meta: res.meta }
  },
  get: async (id: number) => {
    const raw = await apiGet<RawForecastRun>(`/forecast-runs/${id}`)
    return mapRun(raw)
  },
  create: async (payload: Partial<ForecastRun>) => {
    const raw = await apiPost<{ forecast_run_id: number; status: string }>('/forecast-runs', payload)
    return { id: raw.forecast_run_id, status: raw.status, ...payload } as ForecastRun
  },
  remove: (id: number) => apiDelete(`/forecast-runs/${id}`),
  result: async (id: number) => {
    const raw = await apiGet<RawForecastResult>(`/forecast-runs/${id}/results`)
    return mapResult(raw)
  },
  scenarios: async (id: number) => {
    const res = await apiList<Record<string, unknown>>(`/forecast-runs/${id}/scenarios`)
    return res.data.map(mapScenario)
  },
  createScenario: async (id: number, payload: Partial<ForecastScenario>) => {
    const raw = await apiPost<Record<string, unknown>>(`/forecast-runs/${id}/scenarios`, payload)
    return mapScenario(raw)
  },
  removeScenario: (runId: number, scenarioId: number) =>
    apiDelete(`/forecast-runs/${runId}/scenarios/${scenarioId}`),
  scenarioResults: async (runId: number, scenarioId: number) => {
    const [compare] = await Promise.all([
      apiGet<{
        scenario_name: string
        baseline_forecast: number
        scenario_forecast: number
        difference: number
        potential_increase: number
        baseline_target_probability: number | null
        scenario_target_probability: number | null
      }>(`/forecast-runs/${runId}/scenarios/${scenarioId}/compare`),
    ])
    const baseline = Number(compare.baseline_forecast) || 0
    return {
      scenario_id: scenarioId,
      scenario_name: compare.scenario_name,
      current: { p50: baseline, mean: baseline },
      scenario: { p50: Number(compare.scenario_forecast) || 0, mean: Number(compare.scenario_forecast) || 0 },
      potential_increase:
        baseline > 0 ? ((Number(compare.potential_increase) || 0) / baseline) * 100 : 0,
      probability_target:
        compare.scenario_target_probability != null ? Number(compare.scenario_target_probability) / 100 : null,
    } as ScenarioResult
  },
  scenarioCompare: (runId: number, scenarioId: number) =>
    forecastsApi.scenarioResults(runId, scenarioId),
  evaluate: async (id: number) => {
    const [raw, run] = await Promise.all([
      apiGet<{
        status: string
        message?: string
        forecast_mean: number
        actual_revenue: number
        metrics: { mae: number; mape: number; rmse: number }
      }>(`/forecast-runs/${id}/evaluate`),
      forecastsApi.get(id),
    ])
    const period = `${raw.status === 'NO_ACTUAL_DATA' ? '' : run.period_start ?? ''} → ${run.period_end ?? ''}`
    return {
      status: raw.status,
      message: raw.message,
      mae: raw.metrics?.mae ?? 0,
      mape: raw.metrics?.mape ?? 0,
      rmse: raw.metrics?.rmse ?? 0,
      actual: raw.actual_revenue != null ? [{ period, value: raw.actual_revenue }] : [],
      forecast: raw.forecast_mean != null ? [{ period, value: raw.forecast_mean }] : [],
    } as EvaluationResult
  },
  backtest: async (id: number) => {
    const raw = await apiGet<{
      periods: Array<{ period_index: number; forecast: number; actual: number }>
      metrics?: { mae: number; mape: number; rmse: number }
    }>(`/forecast-runs/${id}/backtest`)
    return {
      periods: (raw.periods ?? []).map((p) => ({
        period: `P${p.period_index}`,
        forecast: p.forecast,
        actual: p.actual,
      })),
      metrics: raw.metrics,
    }
  },
}