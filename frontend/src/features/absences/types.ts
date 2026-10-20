/**
 * Absence requests, balances and allowances. Source of truth: the frozen
 * `data_contract` of spec 0278. Minutes are always integers; dates `Y-m-d`.
 */

export const ABSENCE_REQUESTS_DOMAIN = 'absence-requests'

export const ABSENCE_REQUEST_STATUSES = ['draft', 'submitted', 'approved', 'rejected', 'cancelled'] as const
export type AbsenceRequestStatus = (typeof ABSENCE_REQUEST_STATUSES)[number]

/** Tabs of the requests grid. */
export const ABSENCE_SCOPES = ['mine', 'to_approve', 'all'] as const
export type AbsenceScope = (typeof ABSENCE_SCOPES)[number]

export const ABSENCE_REQUESTS_TO_APPROVE_DOMAIN = 'absence-requests-to-approve'
export const ABSENCE_REQUESTS_ALL_DOMAIN = 'absence-requests-all'

/** Each tab is its own table domain (same columns, different row set; the server restricts visibility). */
export const ABSENCE_SCOPE_DOMAINS: Record<AbsenceScope, string> = {
  mine: ABSENCE_REQUESTS_DOMAIN,
  to_approve: ABSENCE_REQUESTS_TO_APPROVE_DOMAIN,
  all: ABSENCE_REQUESTS_ALL_DOMAIN,
}

export interface AbsenceUserRef {
  id: number
  name: string
}

/** Catalogue row (`/absence-types`). */
export interface AbsenceType {
  id: number
  name: string
  deducts_balance: boolean
  requires_approval: boolean
  is_active: boolean
  sort_order: number
  created_at: string
  updated_at: string
}

/** Option of `GET /absence-types/for-select` (active types only). */
export type AbsenceTypeOption = Pick<AbsenceType, 'id' | 'name' | 'deducts_balance' | 'requires_approval'>

export interface AbsenceRequestAttachment {
  id: number
  name: string
  size: number
  mime_type: string
}

export interface AbsenceRequestAbilities {
  update: boolean
  delete: boolean
  submit: boolean
  approve: boolean
  reject: boolean
  cancel: boolean
  attach: boolean
}

export interface AbsenceRequest {
  id: number
  user: AbsenceUserRef
  absence_type: AbsenceTypeOption
  status: AbsenceRequestStatus
  is_full_day: boolean
  starts_on: string
  ends_on: string
  /** Null while draft; fixed on submit. */
  minutes: number | null
  /** Only for `is_full_day = false`: the minutes the person asked for. */
  requested_minutes: number | null
  note: string | null
  rejection_reason: string | null
  /** Snapshot taken on submit; empty while draft. */
  approvers: AbsenceUserRef[]
  decided_by: AbsenceUserRef | null
  decided_at: string | null
  submitted_at: string | null
  cancelled_at: string | null
  attachment: AbsenceRequestAttachment | null
  can: AbsenceRequestAbilities
  created_at: string
  updated_at: string
}

/** POST body. `user_id` is honoured only with `absence-requests.manageAll`. */
export interface CreateAbsenceRequestPayload {
  absence_type_id: number
  is_full_day: boolean
  starts_on: string
  ends_on: string
  requested_minutes?: number | null
  note?: string | null
  submit?: boolean
  user_id?: number
}

/** PATCH body (draft only): same fields as create, without `user_id` and `submit`. */
export type UpdateAbsenceRequestPayload = Omit<CreateAbsenceRequestPayload, 'user_id' | 'submit'>

export interface RejectAbsenceRequestPayload {
  rejection_reason: string
}

export interface AbsenceBalanceRow {
  absence_type: { id: number; name: string }
  annual_minutes: number
  carried_over_minutes: number
  accrued_minutes: number
  used_minutes: number
  pending_minutes: number
  residual_minutes: number
}

export interface AbsenceBalances {
  user: AbsenceUserRef
  year: number
  balances: AbsenceBalanceRow[]
}

export interface AbsenceBalancesParams {
  year?: number
  user_id?: number
}

/** PUT /absence-allowances body (`absence-requests.manageAll`). */
export interface AllowancePayload {
  user_id: number
  absence_type_id: number
  year: number
  annual_minutes: number
  carried_over_minutes: number
}

/** Calendar of spec 0279: the three permission levels, any of them opens the tab. */
export const ABSENCE_CALENDAR_PERMISSIONS = [
  'absence-requests.calendarOwn',
  'absence-requests.calendarTeam',
  'absence-requests.calendarAll',
] as const

export type AbsenceCalendarScope = 'own' | 'team' | 'all'

/** GET /absences/calendar query: at most 62 days, optional function filter. */
export interface AbsenceCalendarParams {
  from: string
  to: string
  business_function_id?: number
}

export interface AbsenceCalendarDay {
  date: string
  is_non_working_day: boolean
  is_holiday: boolean
}

/** One working day of an approved or pending request. */
export interface AbsenceCalendarEntry {
  request_id: number
  date: string
  status: Extract<AbsenceRequestStatus, 'approved' | 'submitted'>
  absence_type_name: string
  is_full_day: boolean
  /** Null for a full day. */
  minutes: number | null
  /** Whether the actor may open the request detail (spec 0278 visibility). */
  can_open: boolean
}

export interface AbsenceCalendarPerson {
  id: number
  name: string
  absences: AbsenceCalendarEntry[]
}

export interface AbsenceCalendar {
  scope: AbsenceCalendarScope
  days: AbsenceCalendarDay[]
  people: AbsenceCalendarPerson[]
}
