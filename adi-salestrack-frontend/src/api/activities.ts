import { apiDelete, apiList, apiPost, apiPut } from './http'

export interface Activity {
  id: number
  opportunity_id: number
  user_id?: number
  user?: { id: number; name: string } | null
  type: string
  subject?: string
  description?: string
  status: string
  scheduled_at?: string | null
  created_at?: string
}

interface RawActivity {
  id: number
  opportunity_id: number
  user?: { id: number; name: string } | null
  type: string
  subject?: string | null
  description?: string | null
  activity_date?: string | null
  due_date?: string | null
  status: string
  created_at?: string
}

function mapActivity(raw: RawActivity): Activity {
  return {
    id: raw.id,
    opportunity_id: raw.opportunity_id,
    user: raw.user,
    type: raw.type,
    subject: raw.subject ?? '',
    description: raw.description ?? '',
    status: raw.status,
    scheduled_at: raw.activity_date ?? null,
    created_at: raw.created_at,
  }
}

export const activitiesApi = {
  list: async (opportunityId: number, params?: Record<string, unknown>) => {
    const res = await apiList<RawActivity>(`/opportunities/${opportunityId}/activities`, { params })
    return { data: res.data.map(mapActivity), meta: res.meta }
  },
  create: (opportunityId: number, payload: Partial<Activity>) =>
    apiPost<Activity>(`/opportunities/${opportunityId}/activities`, toBackendPayload(payload)),
  update: (opportunityId: number, id: number, payload: Partial<Activity>) =>
    apiPut<Activity>(`/opportunities/${opportunityId}/activities/${id}`, toBackendPayload(payload)),
  remove: (opportunityId: number, id: number) =>
    apiDelete(`/opportunities/${opportunityId}/activities/${id}`),
}

function toBackendPayload(payload: Partial<Activity>): Record<string, unknown> {
  return {
    type: payload.type,
    subject: payload.subject ?? null,
    description: payload.description ?? null,
    activity_date: payload.scheduled_at || null,
    status: payload.status,
  }
}