import { apiGet } from './http'
import { opportunitiesApi, type Opportunity } from './opportunities'

export interface RecentOpportunity {
  id: number
  title: string
  stage: string
  status: string
  value: number
  company?: { id: number; name: string } | null
}

export interface TopSales {
  user_id: number
  name: string
  revenue: number
  win_count: number
  win_rate: number
}

export interface DashboardData {
  total_revenue: number
  total_pipeline: number
  won_revenue: number
  win_rate: number
  average_deal_size: number
  forecast_revenue: number | null
  target_probability: number | null
  monthly_revenue?: Array<{ month: string; revenue: number }>
  pipeline_by_stage?: Array<{ stage: string; value: number; count: number }>
  recent_opportunities?: RecentOpportunity[]
  top_sales?: TopSales[]
}

export interface RevenueData {
  data: Array<{ period: string; revenue: number }>
  breakdown?: Record<string, Array<{ label: string; value: number }>>
}

export interface PipelineData {
  total_pipeline: number
  open_count: number
  won_count: number
  lost_count: number
  win_rate: number
  by_stage?: Array<{ stage: string; value: number; count: number }>
}

export interface PerformanceData {
  sales: Array<{
    user_id: number
    name: string
    revenue: number
    opportunities_count: number
    win_count: number
    win_rate: number
    average_deal_size: number
    average_sales_cycle_days?: number
  }>
}

interface RawDashboard {
  year: string
  total_revenue: number
  total_pipeline: number
  won_revenue: number
  win_rate: number
  average_deal_size: number
  open_opportunities: number
  won_opportunities: number
  transaction_count: number
}

interface RawRevenue {
  from: string
  to: string
  total: number
  monthly: Record<string, number>
  by_principal: Array<{ id: number; name: string; revenue: number }>
  by_customer: Array<{ company: { id: number; name: string } | null; revenue: number }>
  by_product: Array<{ id: number; name: string; revenue: number }>
  by_salesperson: Array<{ user: { id: number; name: string } | null; revenue: number }>
}

interface RawPipeline {
  total_pipeline: number
  open: number
  open_value: number
  won: number
  won_value: number
  lost: number
  lost_value: number
  cancelled: number
  cancelled_value?: number
  on_hold: number
  on_hold_value?: number
  win_rate: number
}

interface RawPerformance {
  by_salesperson: Array<{
    user: { id: number; name: string } | null
    deals: number
    revenue: number
    average_deal_size: number
  }>
  by_product: Array<{ id: number; name: string; revenue: number }>
  average_deal_size: number
  average_sales_cycle_days: number
}

function mapMonthly(monthly: Record<string, number>): Array<{ period: string; revenue: number }> {
  return Object.entries(monthly ?? {}).map(([period, revenue]) => ({
    period,
    revenue: Number(revenue) || 0,
  }))
}

function stageBreakdown(pipe: RawPipeline) {
  return [
    { stage: 'OPEN', value: pipe.open_value ?? pipe.total_pipeline, count: pipe.open ?? 0 },
    { stage: 'WON', value: pipe.won_value ?? 0, count: pipe.won ?? 0 },
    { stage: 'LOST', value: pipe.lost_value ?? 0, count: pipe.lost ?? 0 },
    { stage: 'CANCELLED', value: pipe.cancelled_value ?? 0, count: pipe.cancelled ?? 0 },
    { stage: 'ON_HOLD', value: pipe.on_hold_value ?? 0, count: pipe.on_hold ?? 0 },
  ].filter((s) => s.count > 0)
}

function mapRecentOpportunity(op: Opportunity): RecentOpportunity {
  return {
    id: op.id,
    title: op.title,
    stage: op.stage,
    status: op.status,
    value: op.value,
    company: op.company ?? null,
  }
}

function mapTopSales(raw: RawPerformance['by_salesperson']): TopSales[] {
  return (raw ?? []).map((s) => ({
    user_id: s.user?.id ?? 0,
    name: s.user?.name ?? 'Unknown',
    revenue: s.revenue ?? 0,
    win_count: s.deals ?? 0,
    win_rate: 0,
  }))
}

export const analyticsApi = {
  dashboard: async (year?: number) => {
    const [dash, rev, pipe, perf] = await Promise.all([
      apiGet<RawDashboard>('/analytics/dashboard', { params: { year } }),
      apiGet<RawRevenue>('/analytics/revenue', { params: { year } }),
      apiGet<RawPipeline>('/analytics/pipeline'),
      apiGet<RawPerformance>('/analytics/performance', { params: { year } }),
    ])
    let recent: Opportunity[] = []
    try {
      recent = (await opportunitiesApi.list({ per_page: 5, sort_by: 'created_at', sort_dir: 'desc' })).data
    } catch {
      // recent opportunities are optional
    }
    return {
      total_revenue: dash.total_revenue,
      total_pipeline: dash.total_pipeline,
      won_revenue: dash.won_revenue,
      win_rate: dash.win_rate,
      average_deal_size: dash.average_deal_size,
      forecast_revenue: null,
      target_probability: null,
      monthly_revenue: mapMonthly(rev.monthly),
      pipeline_by_stage: stageBreakdown(pipe),
      recent_opportunities: recent.map(mapRecentOpportunity),
      top_sales: mapTopSales(perf.by_salesperson),
    } as unknown as DashboardData
  },
  revenue: async (params?: Record<string, unknown>) => {
    const raw = await apiGet<RawRevenue>('/analytics/revenue', { params })
    const breakdown: Record<string, Array<{ label: string; value: number }>> = {
      principal: (raw.by_principal ?? []).map((r) => ({ label: r.name, value: r.revenue ?? 0 })),
      customer: (raw.by_customer ?? []).map((r) => ({
        label: r.company?.name ?? 'Unknown',
        value: r.revenue ?? 0,
      })),
      product: (raw.by_product ?? []).map((r) => ({ label: r.name, value: r.revenue ?? 0 })),
      salesperson: (raw.by_salesperson ?? []).map((r) => ({
        label: r.user?.name ?? 'Unknown',
        value: r.revenue ?? 0,
      })),
    }
    return {
      data: mapMonthly(raw.monthly),
      breakdown,
    } as RevenueData
  },
  pipeline: async () => {
    const raw = await apiGet<RawPipeline>('/analytics/pipeline')
    return {
      total_pipeline: raw.total_pipeline,
      open_count: raw.open ?? 0,
      won_count: raw.won ?? 0,
      lost_count: raw.lost ?? 0,
      win_rate: raw.win_rate,
      by_stage: stageBreakdown(raw),
    } as PipelineData
  },
  performance: async (params?: Record<string, unknown>) => {
    const raw = await apiGet<RawPerformance>('/analytics/performance', { params })
    return {
      sales: (raw.by_salesperson ?? []).map((s) => ({
        user_id: s.user?.id ?? 0,
        name: s.user?.name ?? 'Unknown',
        revenue: s.revenue ?? 0,
        opportunities_count: 0,
        win_count: s.deals ?? 0,
        win_rate: 0,
        average_deal_size: s.average_deal_size ?? 0,
        average_sales_cycle_days: raw.average_sales_cycle_days ?? null,
      })),
    } as PerformanceData
  },
}