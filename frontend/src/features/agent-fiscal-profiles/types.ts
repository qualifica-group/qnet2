/**
 * Agent fiscal profiles types. Source of truth: the frozen `data_contract` of
 * spec 0212 (`agent_fiscal_profiles`).
 */

import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ResourcePermissions } from '@/features/authorization/types'

/** Domain key shared by the table, the module registry and the ability checks. */
export const AGENT_FISCAL_PROFILES_DOMAIN = 'agent-fiscal-profiles'

export const ENASARCO_MANDATE_TYPES = ['mono', 'pluri'] as const
export type EnasarcoMandateType = (typeof ENASARCO_MANDATE_TYPES)[number]

/** Single profile returned by GET/POST/PUT /agent-fiscal-profiles (envelope `data`). */
export interface AgentFiscalProfileDetail {
  id: number
  referent_id: number
  referent: { id: number; name: string }
  withholding_enabled: boolean
  /** Decimal string (`"23.00"`), null when the withholding is disabled. */
  withholding_rate: string | null
  withholding_base_percent: string | null
  enasarco_enabled: boolean
  enasarco_mandate_type: EnasarcoMandateType | null
  /** VAT rate applied to the agent statements, null = transaction without VAT (spec 0213 D-2). */
  vat_rate_id: number | null
  vat_rate: { id: number; name: string; rate: string } | null
  /** Defaults of the agent statement approval window (spec 0267 D-8). */
  payment_method_id: number | null
  payment_method: { id: number; name: string } | null
  cost_type_id: number | null
  cost_type: { id: number; name: string } | null
  valid_from: string
  valid_until: string | null
  notes: string | null
  created_at: string
  updated_at: string
}

/** Detail carrying the actor's authorization metadata (envelope `permissions`). */
export interface AgentFiscalProfileDetailWithPermissions extends AgentFiscalProfileDetail {
  permissions: ResourcePermissions
}

/** Payload for POST and PUT /agent-fiscal-profiles (same shape). */
export interface AgentFiscalProfilePayload {
  referent_id: number
  withholding_enabled: boolean
  withholding_rate: number | null
  withholding_base_percent: number | null
  enasarco_enabled: boolean
  enasarco_mandate_type: EnasarcoMandateType | null
  vat_rate_id: number | null
  payment_method_id: number | null
  cost_type_id: number | null
  valid_from: string
  valid_until: string | null
  notes: string | null
}

/** Discriminated mode of the form hook: the create draft or the detail's in-place rows. */
export type AgentFiscalProfileFormMode =
  | { type: 'create' }
  | { type: 'edit'; profile: AgentFiscalProfileDetailWithPermissions }

/** The `{id, name}` the relation rows show closed: from the saved detail, or resolved for the create draft. */
export interface AgentFiscalProfileRefs {
  referent: RelationFieldRef | null
  vatRate: RelationFieldRef | null
  paymentMethod: RelationFieldRef | null
  costType: RelationFieldRef | null
}
