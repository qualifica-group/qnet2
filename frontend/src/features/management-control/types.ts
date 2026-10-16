/** Frozen contract of `GET /api/management-control/{income-statement,structure-costs}` (spec 0268). */

export const REVENUE_BASES = ['contract', 'invoiced', 'collected'] as const
export type RevenueBasis = (typeof REVENUE_BASES)[number]

export const SEGMENT_BYS = [
  'none',
  'company',
  'company_site',
  'operational_site',
  'work_order_type',
  'commercial',
] as const
export type SegmentBy = (typeof SEGMENT_BYS)[number]

export const STRUCTURE_GROUP_BYS = ['company', 'company_site', 'operational_site'] as const
export type StructureGroupBy = (typeof STRUCTURE_GROUP_BYS)[number]

export const WORK_ORDER_TYPES = ['processing', 'project'] as const
export type WorkOrderTypeValue = (typeof WORK_ORDER_TYPES)[number]

export type CostNature = 'direct' | 'indirect' | 'general'

/** Key the server gives to the group of records with no value for the dimension. */
export const UNATTRIBUTED_KEY = 'unattributed'

export interface IncomeStatementFigures {
  revenue_contract: number
  revenue_invoiced: number
  revenue_collected: number
  revenue_actual: number
  cost_budget: number
  cost_manual: number
  cost_committed: number
  cost_invoiced: number
  cost_actual: number
  commission_budget: number
  commission_actual: number
  margin_budget: number
  margin_actual: number
  cost_delta: number
  margin_delta: number
}

/** One business function of the statement; amounts are split by revenue share (D-11), counts are not additive (D-23). */
export interface FunctionRow extends IncomeStatementFigures {
  key: string
  label: string | null
  work_orders_count: number
}

export interface SegmentRow extends IncomeStatementFigures {
  key: string
  label: string | null
  work_orders_count: number
  by_business_function: FunctionRow[]
}

export interface IncomeStatementTotals extends IncomeStatementFigures {
  work_orders_count: number
}

export interface IncomeStatementApplied {
  date_from: string | null
  date_to: string | null
  company_id: number[]
  company_site_id: number[]
  operational_site_id: number[]
  business_function_id: number[]
  work_order_type: string[]
  commercial_id: number[]
  revenue_basis: string
  segment_by: string
}

export interface IncomeStatement {
  applied: IncomeStatementApplied
  totals: IncomeStatementTotals
  by_business_function: FunctionRow[]
  segments: SegmentRow[]
}

export interface StructureFigures {
  committed_net: number
  invoiced_net: number
  total_net: number
}

export interface StructureCostTypeRow extends StructureFigures {
  cost_type: { id: number; name: string; color: string | null; nature: CostNature | null } | null
}

export interface StructureDimensionRow extends StructureFigures {
  key: string
  label: string | null
}

export interface StructureCosts {
  applied: {
    date_from: string | null
    date_to: string | null
    company_id: number[]
    company_site_id: number[]
    operational_site_id: number[]
    group_by: string
  }
  totals: StructureFigures
  by_cost_type: StructureCostTypeRow[]
  by_dimension: StructureDimensionRow[]
}

/** Filters common to the pages of the module: period and analytic scope (UI side). */
export interface ScopeFilters {
  dateFrom: string
  dateTo: string
  companyIds: number[]
  companySiteIds: number[]
  operationalSiteIds: number[]
}

/** Filter state of the page (UI side; mapped to the request by `toIncomeStatementParams`). */
export interface IncomeStatementFilters extends ScopeFilters {
  businessFunctionIds: number[]
  workOrderTypes: WorkOrderTypeValue[]
  commercialIds: number[]
  revenueBasis: RevenueBasis
  segmentBy: SegmentBy
}

/** Request query parameters of the income statement, named exactly as the contract. */
export interface IncomeStatementParams {
  date_from?: string
  date_to?: string
  company_id?: number[]
  company_site_id?: number[]
  operational_site_id?: number[]
  business_function_id?: number[]
  work_order_type?: WorkOrderTypeValue[]
  commercial_id?: number[]
  revenue_basis: RevenueBasis
  segment_by: SegmentBy
}

/** Request query parameters of the structure costs, named exactly as the contract. */
export interface StructureCostsParams {
  date_from?: string
  date_to?: string
  company_id?: number[]
  company_site_id?: number[]
  operational_site_id?: number[]
  group_by: StructureGroupBy
}
