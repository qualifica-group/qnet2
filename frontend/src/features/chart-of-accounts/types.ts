/**
 * Chart of accounts types. Source of truth: the frozen `data_contract` of
 * spec 0348 (tree endpoint + chart-accounts / revenue-types CRUD).
 */

import type { CostTypeNature } from '@/features/cost-types/types'

export const CHART_ACCOUNTS_DOMAIN = 'chart-accounts'
export const REVENUE_TYPES_DOMAIN = 'revenue-types'
export const TREASURY_TYPES_DOMAIN = 'treasury-types'

export type ChartAccountKind = 'cost' | 'revenue' | 'financial'
export type ChartAccountLevel = 'group' | 'account'
export type SubAccountSource = 'cost_type' | 'revenue_type' | 'treasury_type'

/** Leaf of the plan: a cost type (cost groups) or a revenue type (revenue groups). */
export interface SubAccount {
  id: number
  source: SubAccountSource
  code: string | null
  name: string
  description: string | null
  is_active: boolean
  /** Only for `source = cost_type`. */
  nature: CostTypeNature | null
  /** Only for `source = cost_type`: a palette token. */
  color: string | null
  /** Only for `source = revenue_type` (spec 0350): the sub-account Entity commission lines get; at most one. */
  is_institution_commission_default?: boolean
}

export interface ChartAccountNode {
  id: number
  code: string
  name: string
  description: string | null
  is_active: boolean
  sub_accounts: SubAccount[]
}

export interface ChartGroup {
  id: number
  code: string
  name: string
  description: string | null
  kind: ChartAccountKind
  is_active: boolean
  accounts: ChartAccountNode[]
}

export interface ResourceAbilities {
  create: boolean
  update: boolean
  delete: boolean
  view_activity: boolean
}

/** The plan adds the spec 0349 export / import gates to the CRUD abilities. */
export interface ChartAccountsAbilities extends ResourceAbilities {
  export: boolean
  import: boolean
}

export interface ChartTreeAbilities {
  chart_accounts: ChartAccountsAbilities
  revenue_types: ResourceAbilities
  /** Spec 0360: financial sub-accounts. */
  treasury_types: ResourceAbilities
  cost_types: ResourceAbilities
}

/** Envelope `data` of GET /chart-of-accounts/tree. */
export interface ChartTree {
  groups: ChartGroup[]
  unplaced_cost_types: SubAccount[]
  abilities: ChartTreeAbilities
}

/** Subset of ChartAccountResource the screen needs back after a write. */
export interface ChartAccountResource {
  id: number
  level: ChartAccountLevel
  parent_id: number | null
  code: string
  name: string
  description: string | null
  kind: ChartAccountKind
  is_active: boolean
}

export interface RevenueTypeResource {
  id: number
  chart_account_id: number
  code: string
  name: string
  description: string | null
  is_active: boolean
}

/** POST /chart-accounts: `parent_id` only for accounts, `kind` only for groups. */
export interface CreateChartAccountPayload {
  level: ChartAccountLevel
  parent_id?: number
  kind?: ChartAccountKind
  code: string
  name: string
  description: string | null
  is_active: boolean
}

/** PATCH /chart-accounts/{id}: `level` is never sent (422). */
export interface UpdateChartAccountPayload {
  parent_id?: number
  kind?: ChartAccountKind
  code?: string
  name?: string
  description?: string | null
  is_active?: boolean
}

/** Spec 0360: same shape as a revenue type. */
export type TreasuryTypeResource = RevenueTypeResource

export interface TreasuryTypePayload {
  chart_account_id: number
  code: string
  name: string
  description: string | null
  is_active: boolean
}

export type UpdateTreasuryTypePayload = Partial<TreasuryTypePayload>

export interface RevenueTypePayload {
  chart_account_id: number
  code: string
  name: string
  description: string | null
  is_active: boolean
  /** Spec 0350: true takes the flag away from the others, server-side. */
  is_institution_commission_default?: boolean
}

export type UpdateRevenueTypePayload = Partial<RevenueTypePayload>

export type ChartExportFormat = 'xlsx' | 'csv'

export interface ChartImportCounts {
  groups: number
  accounts: number
  cost_sub_accounts: number
  revenue_sub_accounts: number
}

export interface ChartImportError {
  /** File row number, header = 1. */
  row: number
  /** Header in the user's language, null for row-level errors. */
  column: string | null
  message: string
}

/** Envelope `data` of POST /chart-of-accounts/import (spec 0349). */
export interface ChartImportResult {
  applied: boolean
  summary: { rows: number; create: ChartImportCounts; update: ChartImportCounts }
  errors: ChartImportError[]
}
