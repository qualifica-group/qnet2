/**
 * ENASARCO parameters types. Source of truth: the frozen `data_contract` of
 * spec 0213 (`enasarco_parameters`).
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** Domain key shared by the table, the module registry and the ability checks. */
export const ENASARCO_PARAMETERS_DOMAIN = 'enasarco-parameters'

/** Single parameter set returned by GET/POST/PUT /enasarco-parameters (envelope `data`). */
export interface EnasarcoParameterDetail {
  id: number
  year: number
  /** Decimal strings (`"17.00"`). */
  total_rate: string
  agent_share_percent: string
  single_firm_ceiling: string
  multi_firm_ceiling: string
  notes: string | null
  created_at: string
  updated_at: string
}

/** Detail carrying the actor's authorization metadata (envelope `permissions`). */
export interface EnasarcoParameterDetailWithPermissions extends EnasarcoParameterDetail {
  permissions: ResourcePermissions
}

/** Payload for POST and PUT /enasarco-parameters (same shape). */
export interface EnasarcoParameterPayload {
  year: number
  total_rate: number
  agent_share_percent: number
  single_firm_ceiling: number
  multi_firm_ceiling: number
  notes: string | null
}

/** Discriminated form mode shared by the form hook/meta-resolver and the form component. */
export type EnasarcoParameterFormMode =
  | { type: 'create' }
  | { type: 'edit'; parameter: EnasarcoParameterDetailWithPermissions }
