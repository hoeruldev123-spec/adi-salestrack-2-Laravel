export const OPPORTUNITY_STAGES = ['LEAD', 'QUALIFICATION', 'PROPOSAL', 'NEGOTIATION', 'CLOSING'] as const
export type OpportunityStage = (typeof OPPORTUNITY_STAGES)[number]

export const OPPORTUNITY_STATUSES = ['OPEN', 'WON', 'LOST', 'CANCELLED', 'ON_HOLD'] as const
export type OpportunityStatus = (typeof OPPORTUNITY_STATUSES)[number]

export const ACTIVITY_TYPES = ['CALL', 'MEETING', 'EMAIL', 'FOLLOW_UP', 'NOTE', 'TASK'] as const
export const ACTIVITY_STATUSES = ['OPEN', 'DONE', 'CANCELLED'] as const

export const PRODUCT_TYPES = ['PRODUCT', 'SERVICE'] as const
export const ENTITY_STATUSES = ['ACTIVE', 'INACTIVE'] as const

export const FORECAST_TYPES = ['HISTORICAL', 'OPPORTUNITY', 'COMBINED', 'SCENARIO'] as const
export const FORECAST_RUN_STATUSES = ['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED'] as const

export const ROLES = ['ADMIN', 'SALES', 'SALES_MANAGER', 'MANAGEMENT'] as const
export type Role = (typeof ROLES)[number]

export const STAGE_PROBABILITY: Record<string, number> = {
  LEAD: 10,
  QUALIFICATION: 30,
  PROPOSAL: 50,
  NEGOTIATION: 70,
  CLOSING: 90,
}

export const OPPORTUNITY_SOURCES = [
  'WEBSITE',
  'REFERRAL',
  'COLD_CALL',
  'EMAIL',
  'SOCIAL_MEDIA',
  'EXHIBITION',
  'OTHER',
] as const

export const TRANSACTION_STATUSES = ['PAID', 'UNPAID', 'CANCELLED', 'REFUNDED'] as const

export const STAGE_BADGE_COLORS: Record<string, string> = {
  LEAD: 'bg-slate-100 text-slate-700',
  QUALIFICATION: 'bg-blue-100 text-blue-700',
  PROPOSAL: 'bg-amber-100 text-amber-700',
  NEGOTIATION: 'bg-purple-100 text-purple-700',
  CLOSING: 'bg-cyan-100 text-cyan-700',
  WON: 'bg-emerald-100 text-emerald-700',
  LOST: 'bg-red-100 text-red-700',
  CANCELLED: 'bg-red-100 text-red-700',
  ON_HOLD: 'bg-orange-100 text-orange-700',
  OPEN: 'bg-emerald-100 text-emerald-700',
  PENDING: 'bg-slate-100 text-slate-600',
  PROCESSING: 'bg-blue-100 text-blue-700',
  COMPLETED: 'bg-emerald-100 text-emerald-700',
  FAILED: 'bg-red-100 text-red-700',
  ACTIVE: 'bg-emerald-100 text-emerald-700',
  INACTIVE: 'bg-slate-100 text-slate-600',
  DONE: 'bg-emerald-100 text-emerald-700',
  DRAFT: 'bg-slate-100 text-slate-600',
  CONFIRMED: 'bg-blue-100 text-blue-700',
  PAID: 'bg-emerald-100 text-emerald-700',
  UNPAID: 'bg-amber-100 text-amber-700',
  REFUNDED: 'bg-slate-100 text-slate-600',
  ADMIN: 'bg-red-100 text-red-700',
  SALES: 'bg-blue-100 text-blue-700',
  SALES_MANAGER: 'bg-purple-100 text-purple-700',
  MANAGEMENT: 'bg-amber-100 text-amber-700',
}

export function badgeColor(value: string | null | undefined): string {
  if (!value) return 'bg-slate-100 text-slate-600'
  return STAGE_BADGE_COLORS[value] ?? 'bg-slate-100 text-slate-600'
}

export interface RoleObject {
  id: number
  name: string
  description?: string | null
}

export function roleNameOf(value: unknown): string {
  if (typeof value === 'string') return value
  if (value && typeof value === 'object') {
    return ((value as RoleObject)?.name ?? '') as string
  }
  return ''
}
