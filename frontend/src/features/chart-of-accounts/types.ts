/**
 * Chart of accounts types. Source of truth: the frozen `data_contract` of
 * spec 0348 (tree endpoint + chart-accounts / revenue-types CRUD).
 */

import type { CostTypeNature } from '@/features/cost-types/types'

export const CHART_ACCOUNTS_DOMAIN = 'chart-accounts'
export const REVENUE_TYPES_DOMAIN = 'revenue-types'

export type ChartAccountKind = 'cost' | 'revenue'
export type ChartAccountLevel = 'group' | 'account'
export type SubAccountSource = 'cost_type' | 'revenue_type'

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

export interface ChartTreeAbilities {
  chart_accounts: ResourceAbilities
  revenue_types: ResourceAbilities
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

export interface RevenueTypePayload {
  chart_account_id: number
  code: string
  name: string
  description: string | null
  is_active: boolean
}

export type UpdateRevenueTypePayload = Partial<RevenueTypePayload>
