/**
 * Company closures catalogue. Source of truth: the frozen `data_contract` of spec 0304
 * (D-13..D-15). Dates are `Y-m-d`.
 */

/** Domain key shared by the table, the module registry and the ability checks. */
export const COMPANY_CLOSURES_DOMAIN = 'company-closures'

export const COMPANY_CLOSURE_KINDS = ['holiday', 'collective_leave'] as const
export type CompanyClosureKind = (typeof COMPANY_CLOSURE_KINDS)[number]

/** Longest closure the server accepts, in days counting both the first and the last. */
export const COMPANY_CLOSURE_MAX_DAYS = 31

export interface CompanyClosureRef {
  id: number
  name: string
}

/** Why a person got no generated request (collective leave only). */
export type ClosureSkipReason = 'overlap' | 'no_working_day'

export interface ClosureGeneration {
  created: number
  skipped: { user: CompanyClosureRef; reason: ClosureSkipReason }[]
}

export interface CompanyClosure {
  id: number
  name: string
  starts_on: string
  ends_on: string
  kind: CompanyClosureKind
  absence_type: CompanyClosureRef | null
  applies_to_all_sites: boolean
  operational_sites: CompanyClosureRef[]
  repeats_yearly: boolean
  /** A started closure cannot be edited or deleted (409), unless it repeats yearly. */
  is_started: boolean
  generated_requests_count: number
  /** Only on the POST/PUT response of a collective leave. */
  generation?: ClosureGeneration
  created_at: string
  updated_at: string
}

/** POST/PUT body. `absence_type_id` only for a collective leave, `operational_site_ids` only for chosen sites. */
export interface CompanyClosurePayload {
  name: string
  starts_on: string
  ends_on: string
  kind: CompanyClosureKind
  absence_type_id?: number
  applies_to_all_sites: boolean
  operational_site_ids?: number[]
  repeats_yearly: boolean
}
