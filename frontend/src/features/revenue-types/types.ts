/** Revenue sub-account types (spec 0350 data_contract, frozen). */
import type { SubAccountGroup } from '@/features/chart-of-accounts/sub-account-select/sub-account-groups'

/** Reference to a revenue sub-account as every resource embeds it. */
export interface RevenueTypeRef {
  id: number
  code: string
  name: string
  /** Human path, e.g. "70 Ricavi delle vendite > 70.01 Prestazioni di servizi". */
  path: string
  is_active: boolean
}

/** One for-select option: the ref plus its group (mastro) and account (conto). */
export interface RevenueTypeOption extends RevenueTypeRef {
  group: { code: string; name: string }
  account: { code: string; name: string }
}

/** `data` of `GET /api/revenue-types/for-select`. */
export interface RevenueTypeForSelectData {
  items: RevenueTypeOption[]
  total: number
  offset: number
}

/** The value a product or category actually uses: its own, or the one inherited from an ancestor category. */
export interface EffectiveRevenueType {
  revenue_type: RevenueTypeRef
  inherited: boolean
  source_category: { id: number; name: string } | null
}

/** Query parameters of the for-select endpoint. */
export interface RevenueTypeForSelectParams {
  search?: string
  ids?: number[]
  offset?: number
  limit?: number
}

/** Options grouped mastro > conto (shared grouping, see `sub-account-select`). */
export type RevenueTypeGroup = SubAccountGroup<RevenueTypeOption>
