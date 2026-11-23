import { apiDelete, apiGet, apiList, apiPost } from './http'

export interface ForecastRun {
  id: number
  name: string
  forecast_name?: string | null
  forecast_type: string
  forecast_scope?: string | null
  revenue_category?: string | null
  period_start?: string | null
  period_end?: string | null
  historical_start_date?: string | null
  historical_end_date?: string | null
  forecast_start_date?: string | null
  forecast_end_date?: string | null
  principal?: { id: number; name: string } | null
  principal_id?: number | null
  warnings?: string[]
  simulation_count: number
  confidence_level: number
  target_revenue?: number | null
  predicted_revenue?: number | null
  best_case?: number | null
  worst_case?: number | null
  most_likely?: number | null
  actual_revenue?: number | null
  mape?: number | null
  status: string
  error_message?: string | null
  created_by?: { id: number; name: string } | null
  started_at?: string | null
  completed_at?: string | null
  created_at?: string
}

export interface ForecastConfiguration {
  forecast_scopes: string[]
  revenue_categories: string[]
  simulation: { default: number; min: number; max: number }
  principals: Array<{ id: number; name: string }>
  historical_period: { available_start: string | null; available_end: string }
  forecast_period: { suggested_start: string; suggested_end: string }
  recommendation_thresholds: Record<string, number>
  opportunity_data: { open_count: number; has_data: boolean }
}

export interface ForecastOpportunity {
  id: number
  title: string
  project_name?: string | null
  client_name?: string | null
  stage: string
  status: string
  principal_id?: number | null
  principal_name?: string | null
  value: number
  probability: number
  probability_percent: number
  probability_source: string
  closing_date?: string | null
  last_activity_at?: string | null
  categories: Record<string, number>
  item_count: number
}

export interface ForecastResultSummary {
  id: number
  forecast_run_id: number
  forecast_name: string
  forecast_period: { start: string; end: string }
  historical_period: { start: string; end: string }
  simulation_count: number
  total_opportunities: number
  total_opportunity_value: number
  expected_opportunity_value: number
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
  expected_revenue: number
  best_case: number
  worst_case: number
  most_likely: number
  actual_revenue: number | null
  mape: number | null
  scenarios: {
    worst_case: number
    most_likely: number
    best_case: number
    expected_revenue: number
  }
  warnings: string[]
  opportunities: ForecastOpportunity[]
}

export interface ForecastDistribution {
  forecast_run_id: number
  iteration_count: number
  histogram: Array<{ bin_start: number; bin_end: number; count: number }>
  bins: Array<{ bin_start: number; bin_end: number; count: number }>
  p10?: number | null
  p25?: number | null
  p50?: number | null
  p75?: number | null
  p90?: number | null
  p95?: number | null
  mean?: number | null
  median?: number | null
  minimum?: number | null
  maximum?: number | null
  series: number[]
}

export interface ForecastBreakdownItem {
  principal_id?: number | null
  principal?: string
  category?: string
  opportunity_count: number
  estimated_value: number
  expected_revenue: number
}

export interface MonthlyEvaluationPeriod {
  period: string
  label: string
  forecast: number
  actual: number
  error_percentage: number | null
  status: string
}

export interface ForecastEvaluation {
  forecast_run_id: number
  status: string
  forecast_period: { start: string; end: string }
  periods: MonthlyEvaluationPeriod[]
  mape: number | null
  interpretation: string
  evaluated_periods: number
  skipped_periods: number
  forecast_revenue: number
  actual_revenue: number
  total_forecast: number
  total_actual: number
}

export interface ForecastRecommendation {
  id: number
  forecast_run_id: number
  opportunity_id: number
  recommendation_type: string
  priority: string
  recommendation_title: string
  recommendation_description: string
  estimated_value: number | null
  probability: number | null
  action_suggestion: string
  forecast_run?: {
    id: number
    forecast_name: string
    forecast_start_date?: string | null
    forecast_end_date?: string | null
  } | null
  opportunity?: {
    id: number
    title: string
    client_name?: string | null
    stage: string
    status: string
    closing_date?: string | null
    estimated_value: number
    principal?: { id: number; name: string } | null
    last_activity_at?: string | null
  } | null
  created_at?: string
}

export interface RecommendationSummary {
  TOTAL: number
  HIGH: number
  MEDIUM: number
  LOW: number
  [key: string]: number
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
  forecast_name?: string | null
  forecast_type: string
  forecast_scope?: string | null
  revenue_category?: string | null
  period_start?: string | null
  period_end?: string | null
  historical_start_date?: string | null
  historical_end_date?: string | null
  forecast_start_date?: string | null
  forecast_end_date?: string | null
  principal?: { id: number; name: string } | null
  principal_id?: number | null
  warnings?: string[]
  simulation_count: number
  confidence_level: number
  target_revenue?: number | null
  predicted_revenue?: number | null
  best_case?: number | null
  worst_case?: number | null
  most_likely?: number | null
  actual_revenue?: number | null
  mape?: number | null
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
    forecast_name: raw.forecast_name,
    forecast_type: raw.forecast_type,
    forecast_scope: raw.forecast_scope,
    revenue_category: raw.revenue_category,
    period_start: raw.period_start,
    period_end: raw.period_end,
    historical_start_date: raw.historical_start_date,
    historical_end_date: raw.historical_end_date,
    forecast_start_date: raw.forecast_start_date,
    forecast_end_date: raw.forecast_end_date,
    principal: raw.principal,
    warnings: raw.warnings ?? [],
    simulation_count: raw.simulation_count,
    confidence_level: raw.confidence_level,
    target_revenue: raw.target_revenue,
    predicted_revenue: raw.predicted_revenue,
    best_case: raw.best_case,
    worst_case: raw.worst_case,
    most_likely: raw.most_likely,
    actual_revenue: raw.actual_revenue,
    mape: raw.mape,
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
    const res = await apiList<RawForecastRun>('/forecasting', { params })
    return { data: res.data.map(mapRun), meta: res.meta }
  },
  get: async (id: number) => {
    const raw = await apiGet<RawForecastRun>(`/forecasting/${id}`)
    return mapRun(raw)
  },
  create: async (payload: Partial<ForecastRun>) => {
    const raw = await apiPost<{ forecast_run_id: number; status: string }>('/forecasting/run', payload)
    return { id: raw.forecast_run_id, status: raw.status, ...payload } as ForecastRun
  },
  remove: (id: number) => apiDelete(`/forecasting/${id}`),
  configuration: () => apiGet<ForecastConfiguration>('/forecasting/configuration'),
  resultSummary: (id: number) => apiGet<ForecastResultSummary | null>(`/forecasting/${id}/results`),
  distribution: (id: number) => apiGet<ForecastDistribution>(`/forecasting/${id}/distribution`),
  principals: (id: number) => apiGet<ForecastBreakdownItem[]>(`/forecasting/${id}/principals`),
  categories: (id: number) => apiGet<ForecastBreakdownItem[]>(`/forecasting/${id}/categories`),
  monthlyEvaluation: (id: number) => apiGet<ForecastEvaluation>(`/forecasting/${id}/evaluation`),
  recommendations: async (id: number, params?: Record<string, unknown>) => {
    const res = await apiList<ForecastRecommendation>(`/forecasting/${id}/recommendations`, { params })
    return { data: res.data, meta: res.meta }
  },
  allRecommendations: async (params?: Record<string, unknown>) => {
    const res = await apiList<ForecastRecommendation>('/recommendations', { params })
    return {
      data: res.data,
      meta: res.meta,
      summary: (res.meta?.summary ?? { TOTAL: 0, HIGH: 0, MEDIUM: 0, LOW: 0 }) as RecommendationSummary,
    }
  },
  recommendation: (id: number) => apiGet<ForecastRecommendation>(`/recommendations/${id}`),
  result: async (id: number) => {
    const raw = await apiGet<RawForecastResult>(`/forecasting/${id}/results`)
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